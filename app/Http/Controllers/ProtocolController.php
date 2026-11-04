<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\ProtocolLetter as R;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * TFC Protocol: the page, and the JSON its screens (public/protocol-app) work with. Secretaries keep letter templates,
 * heads or the protocol office approve them, everyone writes documents from the published ones.
 */
class ProtocolController extends Controller
{
    private const ROLES = ['admin', 'protocol', 'head', 'secretary', 'employee'];

    private function fail(int $status, string $message): never
    {
        throw new HttpResponseException(response()->json(['error' => $message], $status));
    }

    private function need(bool $condition, ?string $message = null): void
    {
        $condition || $this->fail(403, $message ?? 'You do not have permission to do this.');
    }

    private function now(): string
    {
        return now()->toIso8601ZuluString('millisecond');
    }

    // ---- The one person, as Protocol sees them (ids are text to the screens) ----

    private function me(): User
    {
        return auth()->user();
    }

    private function role(User $u): string
    {
        return $u->protocolRole();
    }

    private function isRole(User $u, string ...$roles): bool
    {
        return in_array($this->role($u), $roles, true);
    }

    private function dept(User $u): ?string
    {
        return $u->department_id === null ? null : (string) $u->department_id;
    }

    private function pubUser(User $u): array
    {
        return ['id' => (string) $u->id, 'name' => $u->name, 'username' => $u->email, 'role' => $this->role($u), 'departmentId' => $this->dept($u), 'title' => (string) $u->job_title, 'active' => (bool) $u->is_active];
    }

    private function departments(): array
    {
        return Department::query()->orderBy('name')->get()->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->name, 'code' => (string) $d->code])->all();
    }

    private function deptOf(mixed $id): ?Department
    {
        return is_numeric($id) ? Department::query()->find((int) $id) : null;
    }

    // ---- Records ----

    private function settings(): array
    {
        return json_decode((string) DB::table('protocol_settings')->value('data'), true) ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function all(string $table): array
    {
        return DB::table($table)->orderBy('id')->pluck('data')->map(fn ($d) => json_decode($d, true))->all();
    }

    private function find(string $table, string $id, string $message): array
    {
        $row = DB::table($table)->where('id', $id)->value('data');
        $row === null && $this->fail(404, $message);

        return json_decode($row, true);
    }

    private function put(string $table, array $record): void
    {
        DB::table($table)->updateOrInsert(['id' => $record['id']], ['data' => json_encode($record, JSON_UNESCAPED_UNICODE)]);
    }

    private function log(?User $u, string $action, ?string $detail = ''): void
    {
        DB::table('protocol_audit')->insert(['at' => $this->now(), 'user_id' => $u?->id, 'action' => $action, 'detail' => (string) $detail]);
    }

    private function notify(iterable $ids, string $text, string $link = ''): void
    {
        foreach (collect($ids)->filter()->unique() as $id) {
            DB::table('protocol_notifications')->insert(['user_id' => (int) $id, 'text' => $text, 'link' => $link, 'at' => $this->now(), 'read' => false]);
        }
    }

    // ---- Who may do what ----

    private function canManageTpl(User $u, array $t): bool
    {
        return $this->isRole($u, 'admin', 'protocol') || ($this->role($u) === 'secretary' && $this->dept($u) === $t['departmentId']);
    }

    private function canApproveTpl(User $u, array $t): bool
    {
        return $this->isRole($u, 'admin', 'protocol') || ($this->role($u) === 'head' && $this->dept($u) === $t['departmentId']);
    }

    private function canUseTpl(User $u, array $t): bool
    {
        return ! empty($t['published']) && empty($t['archived']) && (empty($t['published']['confidential']) || $this->dept($u) === $t['departmentId'] || $this->isRole($u, 'admin', 'protocol'));
    }

    private function canSeeTpl(User $u, array $t): bool
    {
        return $this->canUseTpl($u, $t) || $this->canManageTpl($u, $t) || (($t['draft']['status'] ?? null) === 'submitted' && $this->canApproveTpl($u, $t));
    }

    private function canApproveDoc(User $u, array $d): bool
    {
        return $this->role($u) === 'admin' || ($this->role($u) === 'head' && $this->dept($u) === ($d['departmentId'] ?? null));
    }

    private function canSeeDoc(User $u, array $d): bool
    {
        return $d['createdBy'] === (string) $u->id || $this->canApproveDoc($u, $d) || $this->role($u) === 'protocol';
    }

    /** @return list<string> */
    private function activeUsers(callable $pick): array
    {
        return User::query()->where('is_active', true)->get()->filter($pick)->map(fn ($u) => (string) $u->id)->values()->all();
    }

    private function tplApprovers(array $t): array
    {
        $ids = $this->activeUsers(fn ($u) => ($this->role($u) === 'head' && $this->dept($u) === $t['departmentId']) || $this->role($u) === 'protocol');

        return $ids ?: $this->activeUsers(fn ($u) => $this->role($u) === 'admin');
    }

    private function docApprovers(array $d): array
    {
        $ids = $this->activeUsers(fn ($u) => $this->role($u) === 'head' && $this->dept($u) === ($d['departmentId'] ?? null));

        return $ids ?: $this->activeUsers(fn ($u) => $this->role($u) === 'admin');
    }

    private function dueForReview(array $t): bool
    {
        $p = $t['published'] ?? null;
        if (! $p || ! empty($t['archived']) || empty($p['reviewMonths'])) {
            return false;
        }

        return now()->parse($p['approvedAt'])->addMonths((int) $p['reviewMonths'])->isPast();
    }

    private function tplOut(array $t, User $u, ?array $docs = null): array
    {
        $manage = $this->canManageTpl($u, $t);
        $approve = $this->canApproveTpl($u, $t);
        $docs ??= $this->all('protocol_documents');

        return ['id' => $t['id'], 'departmentId' => $t['departmentId'], 'createdBy' => $t['createdBy'], 'createdAt' => $t['createdAt'], 'archived' => (bool) ($t['archived'] ?? false),
            'published' => $t['published'] ?? null, 'draft' => $manage || $approve ? ($t['draft'] ?? null) : null, 'versions' => $t['versions'] ?? [],
            'usage' => count(array_filter($docs, fn ($d) => $d['templateId'] === $t['id'])), 'dueForReview' => $this->dueForReview($t),
            'perms' => ['manage' => $manage, 'approve' => $approve, 'use' => $this->canUseTpl($u, $t)]];
    }

    private function docOut(array $d, User $u): array
    {
        $mine = $d['createdBy'] === (string) $u->id;
        $open = in_array($d['status'], ['draft', 'returned'], true);
        $d['values'] = (object) ($d['values'] ?? []);

        return $d + ['perms' => ['edit' => $mine && $open, 'remove' => ($mine && $open) || $this->role($u) === 'admin',
            'approve' => $d['status'] === 'pending' && $this->canApproveDoc($u, $d) && (! $mine || $this->role($u) === 'admin')]];
    }

    // ---- The page ----

    public function index(): View
    {
        return view('protocol.index');
    }

    public function upload(string $file): Response
    {
        (R::isImage($file) && DB::table('protocol_uploads')->where('file', $file)->exists()) || abort(404);

        return response($this->readUpload($file) ?? abort(404), 200, ['Content-Type' => str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    private function readUpload(string $file): ?string
    {
        if (! R::isImage($file)) {
            return null;
        }
        if (Storage::disk('local')->exists("protocol/{$file}")) {
            return Storage::disk('local')->get("protocol/{$file}");
        }
        $bundled = resource_path("protocol/{$file}");

        return is_file($bundled) ? (string) file_get_contents($bundled) : null;
    }

    // ---- Signing and summary ----

    public function publicInfo(): JsonResponse
    {
        $s = $this->settings();

        return response()->json(['orgName' => $s['orgName'] ?? '', 'logo' => $s['logo'] ?? '', 'demo' => []]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function password(Request $request): JsonResponse
    {
        $u = $this->me();
        Hash::check((string) $request->input('current'), $u->password) || $this->fail(400, 'Your current password is not correct.');
        strlen((string) $request->input('next')) >= 8 || $this->fail(400, 'The new password must have at least 8 characters.');
        $u->password = (string) $request->input('next');
        $u->save();
        $this->log($u, 'Changed password');

        return response()->json(['ok' => true]);
    }

    public function bootstrap(): JsonResponse
    {
        return response()->json(['me' => $this->pubUser($this->me()), 'settings' => $this->settings(), 'departments' => $this->departments(),
            'users' => User::query()->orderBy('name')->get()->map(fn ($u) => $this->pubUser($u))->all()]);
    }

    public function summary(): JsonResponse
    {
        $u = $this->me();
        $id = (string) $u->id;
        $templates = $this->all('protocol_templates');
        $docs = $this->all('protocol_documents');
        $isAdmin = $this->role($u) === 'admin';

        return response()->json([
            'pendingTemplates' => count(array_filter($templates, fn ($t) => ($t['draft']['status'] ?? null) === 'submitted' && $this->canApproveTpl($u, $t) && ($t['draft']['submittedBy'] ?? null) !== $id)),
            'pendingDocs' => count(array_filter($docs, fn ($d) => $d['status'] === 'pending' && $this->canApproveDoc($u, $d) && ($d['createdBy'] !== $id || $isAdmin))),
            'returnedToMe' => count(array_filter($docs, fn ($d) => $d['createdBy'] === $id && $d['status'] === 'returned')) + count(array_filter($templates, fn ($t) => ($t['draft']['status'] ?? null) === 'returned' && $this->canManageTpl($u, $t))),
            'unread' => DB::table('protocol_notifications')->where('user_id', $u->id)->where('read', false)->count(),
            'notifications' => DB::table('protocol_notifications')->where('user_id', $u->id)->orderByDesc('id')->limit(15)->get()->map(fn ($n) => ['id' => (string) $n->id, 'userId' => $id, 'text' => $n->text, 'link' => (string) $n->link, 'at' => $n->at, 'read' => (bool) $n->read])->all(),
            'dueForReview' => array_values(array_map(fn ($t) => ['id' => $t['id'], 'title' => $t['published']['title'], 'approvedAt' => $t['published']['approvedAt']], array_filter($templates, fn ($t) => $this->dueForReview($t) && $this->canManageTpl($u, $t)))),
        ]);
    }

    public function notificationsRead(): JsonResponse
    {
        DB::table('protocol_notifications')->where('user_id', auth()->id())->update(['read' => true]);

        return response()->json(['ok' => true]);
    }

    // ---- Templates ----

    private function cleanContent(mixed $c): array
    {
        $c = is_array($c) ? $c : [];
        $str = fn ($v, int $max) => mb_substr(str_replace("\r", '', (string) ($v ?? '')), 0, $max);
        $list = fn ($v) => array_values(array_filter(array_map(fn ($s) => trim((string) $s), is_array($v) ? $v : explode("\n", (string) ($v ?? '')))));
        $int = fn ($v, int $d) => is_numeric($v) && (int) $v ? (int) $v : $d;
        $clamp = fn ($v, int $lo, int $hi) => max($lo, min($hi, $v));

        $fields = [];
        foreach (is_array($c['fields'] ?? null) ? $c['fields'] : [] as $f) {
            $key = trim($str($f['key'] ?? '', 40));
            if ($key === '') {
                continue;
            }
            $options = is_array($f['options'] ?? null) ? $f['options'] : explode(',', (string) ($f['options'] ?? ''));
            $fields[] = ['key' => $key, 'label' => trim($str($f['label'] ?? '', 100)), 'type' => in_array($f['type'] ?? '', ['text', 'textarea', 'date', 'number', 'select'], true) ? $f['type'] : 'text',
                'required' => ! empty($f['required']), 'help' => $str($f['help'] ?? '', 300), 'example' => $str($f['example'] ?? '', 2000),
                'options' => array_slice(array_values(array_filter(array_map(fn ($s) => trim((string) $s), $options))), 0, 50)];
        }
        $seen = [];
        foreach ($fields as &$f) {
            preg_match('/^[a-z][a-z0-9_]*$/', $f['key']) || $this->fail(400, "Field name \"{$f['key']}\" may only use lowercase letters, numbers and underscores, and must start with a letter.");
            isset(R::SYSTEM_FIELDS[$f['key']]) && $this->fail(400, "\"{$f['key']}\" is filled in automatically and cannot be added as a field.");
            isset($seen[$f['key']]) && $this->fail(400, "The field \"{$f['key']}\" is listed twice.");
            $f['type'] === 'select' && ! $f['options'] && $this->fail(400, 'The field "'.($f['label'] ?: $f['key']).'" is a choice list but has no options.');
            $seen[$f['key']] = true;
            $f['label'] = $f['label'] ?: $f['key'];
        }
        unset($f);

        $logos = null;
        if (is_array($c['logos'] ?? null)) {
            count($c['logos']) > R::MAX_LOGOS && $this->fail(400, 'A letterhead can have at most '.R::MAX_LOGOS.' logos.');
            $given = array_values(array_filter(array_map(fn ($l) => ['file' => (string) ($l['file'] ?? ''), 'height' => $clamp($int($l['height'] ?? null, 84), 30, 200)] + (is_array($l) ? $l : []), $c['logos']), fn ($l) => R::isImage($l['file'])));
            $logos = array_map(fn ($l) => ['file' => $l['file'], 'x' => R::clampX((float) $l['x'], R::logoWidth($l)), 'y' => $clamp((int) round((float) $l['y']), 0, 400), 'height' => $l['height']], R::normLogos($given));
        }
        $headerImage = null;
        if (R::isImage((string) ($c['headerImage']['file'] ?? ''))) {
            $headerImage = ['file' => (string) $c['headerImage']['file'], 'height' => $clamp($int($c['headerImage']['height'] ?? null, 60), 10, 200), 'align' => in_array($c['headerImage']['align'] ?? '', R::LOGO_POSITIONS, true) ? $c['headerImage']['align'] : 'center'];
        }
        $footerImages = array_values(array_filter(array_map(fn ($l) => ['file' => (string) ($l['file'] ?? ''), 'position' => in_array($l['position'] ?? '', R::LOGO_POSITIONS, true) ? $l['position'] : 'center', 'height' => $clamp($int($l['height'] ?? null, 50), 20, 140)],
            array_slice(is_array($c['footerImages'] ?? null) ? $c['footerImages'] : [], 0, 3)), fn ($l) => R::isImage($l['file'])));
        count(array_unique(array_column($footerImages, 'position'))) !== count($footerImages) && $this->fail(400, 'Only one footer image can be placed in each position (left, center, right).');
        $top = $c['headerTextTop'] ?? null;

        return [
            'title' => trim($str($c['title'] ?? '', 200)),
            'category' => trim($str($c['category'] ?? '', 80)) ?: 'Official Letter',
            'description' => $str($c['description'] ?? '', 1000),
            'whenToUse' => $str($c['whenToUse'] ?? '', 3000),
            'audience' => $str($c['audience'] ?? '', 2000),
            'signatory' => $str($c['signatory'] ?? '', 500),
            'attachments' => array_slice($list($c['attachments'] ?? ''), 0, 30),
            'confidential' => ! empty($c['confidential']),
            'requiresApproval' => ! empty($c['requiresApproval']),
            'reviewMonths' => is_numeric($c['reviewMonths'] ?? null) ? $clamp((int) $c['reviewMonths'], 0, 60) : 12,
            'body' => $str($c['body'] ?? '', 50000),
            'fields' => $fields,
            'logos' => $logos,
            'headerTextTop' => $top === null || $top === '' ? null : $clamp((int) round((float) $top), 0, 400),
            'headerImage' => $headerImage,
            'footerImages' => $footerImages,
            'bodyImages' => array_map(function ($b) use ($int, $clamp) {
                $height = $clamp($int($b['height'] ?? null, 80), 10, 800);

                return ['file' => (string) $b['file'], 'x' => R::clampX((float) ($b['x'] ?? 0), R::logoWidth(['file' => $b['file'], 'height' => $height])), 'y' => $clamp((int) round((float) ($b['y'] ?? 0)), 0, 3000), 'height' => $height];
            }, array_slice(R::freeImages($c['bodyImages'] ?? []), 0, 20)),
        ];
    }

    private function checkContentComplete(array $c): void
    {
        $c['title'] !== '' || $this->fail(400, 'Please give the template a title.');
        trim($c['body']) !== '' || $this->fail(400, 'The letter body is empty.');
        $defined = array_column($c['fields'], 'key');
        $unknown = array_values(array_filter(R::fieldKeys($c['body']), fn ($k) => ! in_array($k, $defined, true) && ! isset(R::SYSTEM_FIELDS[$k])));
        $unknown && $this->fail(400, 'These placeholders are used in the letter but not defined as fields: '.implode(', ', array_map(fn ($k) => '{{'.$k.'}}', $unknown)).'. Use "Detect fields from letter" to add them.');
        $files = [...R::imageKeys($c['body']), ...array_column($c['logos'] ?? [], 'file'), ...(($c['headerImage'] ?? null) ? [$c['headerImage']['file']] : []), ...array_column($c['footerImages'] ?? [], 'file'), ...array_column($c['bodyImages'] ?? [], 'file')];
        $have = DB::table('protocol_uploads')->pluck('file')->all();
        $missing = array_values(array_diff($files, $have));
        $missing && $this->fail(400, 'These images were not found: '.implode(', ', $missing).'. Upload them again.');
    }

    public function templates(): JsonResponse
    {
        $u = $this->me();
        $docs = $this->all('protocol_documents');

        return response()->json(array_values(array_map(fn ($t) => $this->tplOut($t, $u, $docs), array_filter($this->all('protocol_templates'), fn ($t) => $this->canSeeTpl($u, $t)))));
    }

    private function tpl(string $id): array
    {
        return $this->find('protocol_templates', $id, 'Template not found');
    }

    public function template(string $id): JsonResponse
    {
        $t = $this->tpl($id);
        $this->need($this->canSeeTpl($this->me(), $t), 'This template is not available to you.');

        return response()->json($this->tplOut($t, $this->me()));
    }

    public function templateStore(Request $request): JsonResponse
    {
        $u = $this->me();
        $this->need($this->isRole($u, 'admin', 'protocol', 'secretary'), 'Only secretaries and administrators can create templates.');
        $departmentId = $this->role($u) === 'secretary' ? $this->dept($u) : $request->input('departmentId');
        $this->deptOf($departmentId) || $this->fail(400, 'Please choose a department.');
        $content = $this->cleanContent($request->input('content'));
        $content['title'] !== '' || $this->fail(400, 'Please give the template a title.');
        $t = ['id' => Str::lower(Str::random(16)), 'departmentId' => (string) $departmentId, 'createdBy' => (string) $u->id, 'createdAt' => $this->now(), 'archived' => false, 'published' => null, 'versions' => [],
            'draft' => $content + ['status' => 'draft', 'updatedBy' => (string) $u->id, 'updatedAt' => $this->now(), 'reviewNote' => '']];
        $this->put('protocol_templates', $t);
        $this->log($u, 'Created template draft', $content['title']);

        return response()->json($this->tplOut($t, $u));
    }

    public function templateUpdate(Request $request, string $id): JsonResponse
    {
        $u = $this->me();
        $t = $this->tpl($id);
        $this->need($this->canManageTpl($u, $t));
        $content = $this->cleanContent($request->input('content'));
        $content['title'] !== '' || $this->fail(400, 'Please give the template a title.');
        if ($request->filled('departmentId') && $this->isRole($u, 'admin', 'protocol')) {
            $this->deptOf($request->input('departmentId')) || $this->fail(400, 'Unknown department.');
            $t['departmentId'] = (string) $request->input('departmentId');
        }
        $t['draft'] = $content + ['status' => 'draft', 'updatedBy' => (string) $u->id, 'updatedAt' => $this->now(), 'reviewNote' => $t['draft']['reviewNote'] ?? ''];
        $this->put('protocol_templates', $t);
        $this->log($u, 'Edited template draft', $content['title']);

        return response()->json($this->tplOut($t, $u));
    }

    public function templateSubmit(string $id): JsonResponse
    {
        $u = $this->me();
        $t = $this->tpl($id);
        $this->need($this->canManageTpl($u, $t));
        empty($t['draft']) && $this->fail(400, 'There are no changes to submit.');
        $this->checkContentComplete($t['draft']);
        $t['draft'] = array_merge($t['draft'], ['status' => 'submitted', 'submittedBy' => (string) $u->id, 'submittedAt' => $this->now()]);
        $this->put('protocol_templates', $t);
        $this->notify(array_diff($this->tplApprovers($t), [(string) $u->id]), "{$u->name} submitted the template \"{$t['draft']['title']}\" for approval", "#/t/{$t['id']}/review");
        $this->log($u, 'Submitted template for approval', $t['draft']['title']);

        return response()->json($this->tplOut($t, $u));
    }

    public function templateApprove(Request $request, string $id): JsonResponse
    {
        $u = $this->me();
        $t = $this->tpl($id);
        $this->need($this->canApproveTpl($u, $t));
        ($t['draft']['status'] ?? null) === 'submitted' || $this->fail(400, 'This template is not waiting for approval.');
        ($t['draft']['submittedBy'] ?? null) === (string) $u->id && $this->role($u) !== 'admin' && $this->fail(403, 'You cannot approve a template you submitted yourself.');
        $this->checkContentComplete($t['draft']);
        $by = $t['draft']['submittedBy'];
        $content = $this->cleanContent($t['draft']) + ['submittedBy' => $by];
        $version = (($t['published']['version'] ?? 0)) + 1;
        $t['published'] = $content + ['version' => $version, 'approvedBy' => (string) $u->id, 'approvedAt' => $this->now(), 'note' => mb_substr((string) $request->input('note', ''), 0, 1000)];
        $t['versions'][] = $t['published'];
        $t['draft'] = null;
        $this->put('protocol_templates', $t);
        $this->notify([$by], "Your template \"{$content['title']}\" was approved and published as version {$version}", "#/t/{$t['id']}");
        $this->notify(array_diff($this->activeUsers(fn ($x) => $this->dept($x) === $t['departmentId']), [(string) $u->id, $by]),
            ($version === 1 ? 'New template available' : "Template updated to version {$version}").": \"{$content['title']}\"", "#/t/{$t['id']}");
        $this->log($u, 'Approved and published template', "{$content['title']} (v{$version})");

        return response()->json($this->tplOut($t, $u));
    }

    public function templateReturn(Request $request, string $id): JsonResponse
    {
        $u = $this->me();
        $t = $this->tpl($id);
        $this->need($this->canApproveTpl($u, $t));
        ($t['draft']['status'] ?? null) === 'submitted' || $this->fail(400, 'This template is not waiting for approval.');
        $note = trim((string) $request->input('note', ''));
        $note !== '' || $this->fail(400, 'Please explain what needs to be corrected.');
        $t['draft'] = array_merge($t['draft'], ['status' => 'returned', 'reviewNote' => mb_substr($note, 0, 2000), 'returnedBy' => (string) $u->id, 'returnedAt' => $this->now()]);
        $this->put('protocol_templates', $t);
        $this->notify([$t['draft']['submittedBy']], "Your template \"{$t['draft']['title']}\" was returned for corrections: ".mb_substr($note, 0, 120), "#/edit/{$t['id']}");
        $this->log($u, 'Returned template for corrections', $t['draft']['title']);

        return response()->json($this->tplOut($t, $u));
    }

    public function templateRestore(Request $request, string $id): JsonResponse
    {
        $u = $this->me();
        $t = $this->tpl($id);
        $this->need($this->canManageTpl($u, $t));
        $v = collect($t['versions'] ?? [])->firstWhere('version', (int) $request->input('version')) ?? $this->fail(404, 'Version not found');
        $t['draft'] = $this->cleanContent($v) + ['status' => 'draft', 'updatedBy' => (string) $u->id, 'updatedAt' => $this->now(), 'reviewNote' => ''];
        $this->put('protocol_templates', $t);
        $this->log($u, 'Restored old version into draft', "{$v['title']} (v{$v['version']})");

        return response()->json($this->tplOut($t, $u));
    }

    public function templateDiscardDraft(string $id): JsonResponse
    {
        $u = $this->me();
        $t = $this->tpl($id);
        $this->need($this->canManageTpl($u, $t));
        $title = $t['draft']['title'] ?? '';
        $t['draft'] = null;
        $deleted = empty($t['published']);
        $deleted ? DB::table('protocol_templates')->where('id', $id)->delete() : $this->put('protocol_templates', $t);
        $this->log($u, 'Discarded template draft', $title);

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    public function templateArchive(Request $request, string $id): JsonResponse
    {
        $u = $this->me();
        $t = $this->tpl($id);
        $this->need($this->canManageTpl($u, $t) || $this->canApproveTpl($u, $t));
        $t['archived'] = (bool) $request->input('archived');
        $this->put('protocol_templates', $t);
        $this->log($u, $t['archived'] ? 'Archived template' : 'Restored template', ($t['published'] ?? $t['draft'] ?? [])['title'] ?? '');

        return response()->json($this->tplOut($t, $u));
    }

    // ---- Documents ----

    private function doc(string $id): array
    {
        return $this->find('protocol_documents', $id, 'Document not found');
    }

    private function assignRef(array &$d): void
    {
        if (! empty($d['ref'])) {
            return;
        }
        $code = ($this->deptOf($d['departmentId'] ?? null)?->code) ?: 'GEN';
        $year = now()->format('Y');
        $key = "{$code}-{$year}";
        $n = DB::transaction(function () use ($key) {
            DB::table('protocol_counters')->insertOrIgnore(['key' => $key, 'n' => 0]);
            DB::table('protocol_counters')->where('key', $key)->increment('n');

            return (int) DB::table('protocol_counters')->where('key', $key)->value('n');
        });
        $s = $this->settings();
        $d['ref'] = str_replace(['{ORG}', '{DEPT}', '{YEAR}', '{SEQ}'], [$s['orgCode'] ?? '', $code, $year, str_pad((string) $n, 4, '0', STR_PAD_LEFT)], $s['refFormat'] ?? '{ORG}/{DEPT}/{YEAR}/{SEQ}');
    }

    private function docTitle(array $tpl, array $values): string
    {
        if (trim((string) ($values['subject'] ?? '')) !== '') {
            return mb_substr(trim((string) $values['subject']), 0, 200);
        }
        $first = collect($tpl['fields'])->first(fn ($f) => ! empty($f['required']) && ! empty($values[$f['key']]));

        return $tpl['title'].($first ? ' – '.mb_substr(explode("\n", (string) $values[$first['key']])[0], 0, 80) : '');
    }

    private function cleanValues(array $tpl, mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $out = [];
        foreach ([...array_column($tpl['fields'], 'key'), 'date', 'sender_name', 'sender_title'] as $k) {
            if (isset($raw[$k])) {
                $out[$k] = mb_substr(str_replace("\r", '', (string) $raw[$k]), 0, 20000);
            }
        }

        return $out;
    }

    private function checkDocComplete(array $d): void
    {
        $missing = array_column(array_filter($d['tpl']['fields'], fn ($f) => ! empty($f['required']) && trim((string) ($d['values'][$f['key']] ?? '')) === ''), 'label');
        trim((string) ($d['values']['date'] ?? '')) === '' && $missing[] = 'Date';
        trim((string) ($d['values']['sender_name'] ?? '')) === '' && $missing[] = 'Signatory name';
        $missing && $this->fail(400, 'Please complete: '.implode(', ', $missing));
        ! empty($d['tpl']['attachments']) && empty($d['attachmentsConfirmed']) && $this->fail(400, 'Please confirm that the required attachments are included.');
    }

    /** Moves a document on for "save" or "complete" (finalize, or submit for approval). */
    private function applyDocAction(array &$d, mixed $action, User $u): void
    {
        $step = fn (string $what, string $note = '') => ['at' => $this->now(), 'by' => (string) $u->id, 'action' => $what, 'note' => $note];
        if ($action === 'complete') {
            $this->checkDocComplete($d);
            if (! empty($d['tpl']['requiresApproval'])) {
                $d['status'] = 'pending';
                $d['history'][] = $step('Submitted for approval');
                $this->notify(array_diff($this->docApprovers($d), [(string) $u->id]), "{$u->name} submitted \"{$d['title']}\" for your approval", "#/doc/{$d['id']}");
                $this->log($u, 'Submitted document', $d['title']);
            } else {
                $d['status'] = 'final';
                $this->assignRef($d);
                $d['history'][] = $step('Generated final letter', $d['ref']);
                $this->log($u, 'Generated document', "{$d['ref']} – {$d['title']}");
            }
        } else {
            $d['status'] = 'draft';
            $d['history'][] = $step('Saved draft');
        }
    }

    public function documents(Request $request): JsonResponse
    {
        $u = $this->me();
        $id = (string) $u->id;
        $scope = (string) $request->query('scope', 'mine');
        $scope === 'all' && $this->need($this->isRole($u, 'admin', 'protocol'));
        $list = array_filter($this->all('protocol_documents'), fn ($d) => match ($scope) {
            'review' => $this->canApproveDoc($u, $d) && $d['createdBy'] !== $id,
            'all' => true,
            default => $d['createdBy'] === $id,
        });
        usort($list, fn ($a, $b) => strcmp($b['updatedAt'], $a['updatedAt']));

        return response()->json(array_map(fn ($d) => $this->docOut($d, $u), $list));
    }

    public function document(string $id): JsonResponse
    {
        $d = $this->doc($id);
        $this->need($this->canSeeDoc($this->me(), $d), 'This document is not available to you.');

        return response()->json($this->docOut($d, $this->me()));
    }

    public function documentStore(Request $request): JsonResponse
    {
        $u = $this->me();
        $t = $this->tpl((string) $request->input('templateId'));
        $this->need($this->canUseTpl($u, $t), 'This template is not available to you.');
        $p = $t['published'];
        $tpl = ['title' => $p['title'], 'category' => $p['category'], 'version' => $p['version'], 'body' => $p['body'], 'fields' => $p['fields'], 'logos' => $p['logos'] ?? null, 'headerTextTop' => $p['headerTextTop'] ?? null,
            'headerImage' => $p['headerImage'] ?? null, 'footerImages' => $p['footerImages'] ?? [], 'bodyImages' => $p['bodyImages'] ?? [], 'attachments' => $p['attachments'], 'requiresApproval' => $p['requiresApproval'],
            'signatory' => $p['signatory'], 'whenToUse' => $p['whenToUse'], 'audience' => $p['audience']];
        $values = $this->cleanValues($tpl, $request->input('values'));
        $d = ['id' => Str::lower(Str::random(16)), 'templateId' => $t['id'], 'departmentId' => $this->dept($u), 'createdBy' => (string) $u->id, 'createdAt' => $this->now(), 'updatedAt' => $this->now(),
            'status' => 'draft', 'ref' => '', 'title' => $this->docTitle($tpl, $values), 'values' => $values, 'attachmentsConfirmed' => (bool) $request->input('attachmentsConfirmed'), 'reviewNote' => '', 'tpl' => $tpl, 'history' => []];
        $this->applyDocAction($d, $request->input('action'), $u);
        $this->put('protocol_documents', $d);

        return response()->json($this->docOut($d, $u));
    }

    public function documentUpdate(Request $request, string $id): JsonResponse
    {
        $u = $this->me();
        $d = $this->doc($id);
        $this->need($d['createdBy'] === (string) $u->id && in_array($d['status'], ['draft', 'returned'], true), 'This document can no longer be edited.');
        $d['values'] = $this->cleanValues($d['tpl'], $request->input('values'));
        $d['title'] = $this->docTitle($d['tpl'], $d['values']);
        $d['attachmentsConfirmed'] = (bool) $request->input('attachmentsConfirmed');
        $d['updatedAt'] = $this->now();
        $this->applyDocAction($d, $request->input('action'), $u);   // an incomplete document stops here, unsaved
        $this->put('protocol_documents', $d);

        return response()->json($this->docOut($d, $u));
    }

    public function documentApprove(Request $request, string $id): JsonResponse
    {
        $u = $this->me();
        $d = $this->doc($id);
        $this->need($this->docOut($d, $u)['perms']['approve'], 'You cannot approve this document.');
        $d['status'] = 'final';
        $this->assignRef($d);
        $d = array_merge($d, ['approvedBy' => (string) $u->id, 'approvedAt' => $this->now(), 'updatedAt' => $this->now(), 'reviewNote' => '']);
        $d['history'][] = ['at' => $this->now(), 'by' => (string) $u->id, 'action' => 'Approved', 'note' => mb_substr((string) $request->input('note', ''), 0, 1000)];
        $this->put('protocol_documents', $d);
        $this->notify([$d['createdBy']], "\"{$d['title']}\" was approved. Reference: {$d['ref']}", "#/doc/{$d['id']}");
        $this->log($u, 'Approved document', "{$d['ref']} – {$d['title']}");

        return response()->json($this->docOut($d, $u));
    }

    public function documentReturn(Request $request, string $id): JsonResponse
    {
        $u = $this->me();
        $d = $this->doc($id);
        $this->need($this->docOut($d, $u)['perms']['approve'], 'You cannot review this document.');
        $note = trim((string) $request->input('note', ''));
        $note !== '' || $this->fail(400, 'Please explain what needs to be corrected.');
        $d = array_merge($d, ['status' => 'returned', 'reviewNote' => mb_substr($note, 0, 2000), 'updatedAt' => $this->now()]);
        $d['history'][] = ['at' => $this->now(), 'by' => (string) $u->id, 'action' => 'Returned for corrections', 'note' => $note];
        $this->put('protocol_documents', $d);
        $this->notify([$d['createdBy']], "\"{$d['title']}\" was returned for corrections: ".mb_substr($note, 0, 120), "#/doc/{$d['id']}");
        $this->log($u, 'Returned document', $d['title']);

        return response()->json($this->docOut($d, $u));
    }

    public function documentDelete(string $id): JsonResponse
    {
        $u = $this->me();
        $d = $this->doc($id);
        $this->need($this->docOut($d, $u)['perms']['remove'], 'This document cannot be deleted.');
        DB::table('protocol_documents')->where('id', $id)->delete();
        $this->log($u, 'Deleted document', ($d['ref'] ?? '') ?: $d['title']);

        return response()->json(['ok' => true]);
    }

    /** The letter as a Word file, or the page that prints it (drawn in the browser by render.js, as on screen). */
    public function documentExport(Request $request, string $id): Response|View
    {
        $u = $this->me();
        $d = $this->doc($id);
        $this->need($this->canSeeDoc($u, $d), 'This document is not available to you.');
        $final = $d['status'] === 'final';
        $settings = $this->settings();
        $department = $this->deptOf($d['departmentId'] ?? null)?->name ?? '';
        $values = ($d['values'] ?? []) + ['ref' => ($d['ref'] ?? '') ?: 'To be assigned', 'department' => $department, 'org_name' => $settings['orgName'] ?? ''];
        $letter = ['settings' => $settings, 'departmentName' => $department, 'logos' => $d['tpl']['logos'] ?? null, 'headerTextTop' => $d['tpl']['headerTextTop'] ?? null, 'headerImage' => $d['tpl']['headerImage'] ?? null,
            'footerImages' => $d['tpl']['footerImages'] ?? [], 'bodyImages' => $d['tpl']['bodyImages'] ?? [], 'body' => $d['tpl']['body'], 'fields' => $d['tpl']['fields'], 'values' => $values, 'preview' => ! $final];
        $word = $request->query('format') === 'doc';
        $this->log($u, $word ? 'Downloaded Word document' : 'Printed document', ($d['ref'] ?? '') ?: $d['title']);
        if (! $word) {
            return view('protocol.print', ['title' => ($d['ref'] ?? '') ?: $d['title'], 'letter' => $letter + ['watermark' => $final ? '' : 'DRAFT', 'assetBase' => '/protocol/uploads/']]);
        }
        $bytes = R::docx($letter + ['draftNotice' => $final ? '' : 'DRAFT – NOT AN OFFICIAL DOCUMENT'], fn ($file) => $this->readUpload($file));
        $name = mb_substr(preg_replace('/[^A-Za-z0-9 _-]+/', '-', ($d['ref'] ?? '') ?: $d['title']), 0, 80) ?: 'document';

        return response($bytes, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Content-Disposition' => "attachment; filename=\"{$name}.docx\""]);
    }

    // ---- The protocol handbook ----

    private function canEditRules(User $u): bool
    {
        return $this->isRole($u, 'admin', 'protocol');
    }

    private function cleanRule(Request $request): array
    {
        $title = mb_substr(trim((string) $request->input('title', '')), 0, 200);
        $title !== '' || $this->fail(400, 'Please enter a title.');

        return ['title' => $title, 'category' => mb_substr(trim((string) $request->input('category', 'General')), 0, 80) ?: 'General', 'body' => mb_substr(str_replace("\r", '', (string) $request->input('body', '')), 0, 20000)];
    }

    public function rules(): JsonResponse
    {
        return response()->json($this->all('protocol_rules'));
    }

    public function ruleStore(Request $request): JsonResponse
    {
        $u = $this->me();
        $this->need($this->canEditRules($u));
        $e = ['id' => Str::lower(Str::random(16))] + $this->cleanRule($request) + ['updatedBy' => (string) $u->id, 'updatedAt' => $this->now(), 'acks' => []];
        $this->put('protocol_rules', $e);
        $this->notify(array_diff($this->activeUsers(fn () => true), [(string) $u->id]), "New office protocol rule: \"{$e['title']}\"", '#/protocol');
        $this->log($u, 'Added protocol rule', $e['title']);

        return response()->json($e);
    }

    public function ruleUpdate(Request $request, string $id): JsonResponse
    {
        $u = $this->me();
        $this->need($this->canEditRules($u));
        $e = array_merge($this->find('protocol_rules', $id, 'Not found'), $this->cleanRule($request), ['updatedBy' => (string) $u->id, 'updatedAt' => $this->now(), 'acks' => []]);
        $this->put('protocol_rules', $e);
        $this->notify(array_diff($this->activeUsers(fn () => true), [(string) $u->id]), "Office protocol rule updated: \"{$e['title']}\" – please read it again", '#/protocol');
        $this->log($u, 'Updated protocol rule', $e['title']);

        return response()->json($e);
    }

    public function ruleDelete(string $id): JsonResponse
    {
        $u = $this->me();
        $this->need($this->canEditRules($u));
        $e = $this->find('protocol_rules', $id, 'Not found');
        DB::table('protocol_rules')->where('id', $id)->delete();
        $this->log($u, 'Deleted protocol rule', $e['title']);

        return response()->json(['ok' => true]);
    }

    public function ruleAck(string $id): JsonResponse
    {
        $e = $this->find('protocol_rules', $id, 'Not found');
        if (! in_array((string) auth()->id(), $e['acks'], true)) {
            $e['acks'][] = (string) auth()->id();
            $this->put('protocol_rules', $e);
        }

        return response()->json($e);
    }

    // ---- Reports and audit ----

    public function reports(): JsonResponse
    {
        $u = $this->me();
        $this->need($this->role($u) !== 'employee', 'Reports are available to secretaries, heads and administrators.');
        $scoped = $this->isRole($u, 'admin', 'protocol') ? null : $this->dept($u);
        $allTemplates = $this->all('protocol_templates');
        $allDocs = $this->all('protocol_documents');
        $tpls = array_filter($allTemplates, fn ($t) => ! $scoped || $t['departmentId'] === $scoped);
        $docs = array_filter($allDocs, fn ($d) => ! $scoped || ($d['departmentId'] ?? null) === $scoped);
        $count = fn (array $arr, callable $pick) => count(array_filter($arr, $pick));
        $usage = array_count_values(array_column($docs, 'templateId'));
        $people = User::query()->where('is_active', true)->get()->filter(fn ($x) => ! $scoped || $this->dept($x) === $scoped);
        $ids = $people->map(fn ($x) => (string) $x->id)->values()->all();
        $top = array_map(fn ($t) => ['id' => $t['id'], 'title' => $t['published']['title'], 'departmentId' => $t['departmentId'], 'count' => $usage[$t['id']] ?? 0],
            array_filter($allTemplates, fn ($t) => ! empty($t['published']) && (! $scoped || $t['departmentId'] === $scoped || isset($usage[$t['id']]))));
        usort($top, fn ($a, $b) => $b['count'] <=> $a['count']);

        return response()->json([
            'scopedTo' => $scoped,
            'templates' => ['published' => $count($tpls, fn ($t) => ! empty($t['published']) && empty($t['archived'])), 'awaiting' => $count($tpls, fn ($t) => ($t['draft']['status'] ?? null) === 'submitted'),
                'drafts' => $count($tpls, fn ($t) => ! empty($t['draft']) && $t['draft']['status'] !== 'submitted'), 'archived' => $count($tpls, fn ($t) => ! empty($t['archived']))],
            'documents' => ['total' => count($docs), 'final' => $count($docs, fn ($d) => $d['status'] === 'final'), 'pending' => $count($docs, fn ($d) => $d['status'] === 'pending'), 'returned' => $count($docs, fn ($d) => $d['status'] === 'returned'), 'draft' => $count($docs, fn ($d) => $d['status'] === 'draft')],
            'byDepartment' => array_values(array_map(fn ($dep) => ['departmentId' => $dep['id'],
                'templates' => $count($allTemplates, fn ($t) => $t['departmentId'] === $dep['id'] && ! empty($t['published']) && empty($t['archived'])),
                'documents' => $count($allDocs, fn ($d) => ($d['departmentId'] ?? null) === $dep['id']),
                'returned' => $count($allDocs, fn ($d) => ($d['departmentId'] ?? null) === $dep['id'] && collect($d['history'])->contains('action', 'Returned for corrections')),
                'staff' => User::query()->where('is_active', true)->where('department_id', $dep['id'])->count(),
            ], array_filter($this->departments(), fn ($dep) => ! $scoped || $dep['id'] === $scoped))),
            'topTemplates' => array_slice($top, 0, 10),
            'dueForReview' => array_values(array_map(fn ($t) => ['id' => $t['id'], 'title' => $t['published']['title'], 'approvedAt' => $t['published']['approvedAt'], 'reviewMonths' => $t['published']['reviewMonths']], array_filter($tpls, fn ($t) => $this->dueForReview($t)))),
            'acknowledgements' => array_map(fn ($e) => ['id' => $e['id'], 'title' => $e['title'], 'count' => count(array_intersect($e['acks'], $ids)), 'total' => count($ids)], $this->all('protocol_rules')),
        ]);
    }

    public function audit(): JsonResponse
    {
        $this->need($this->isRole($this->me(), 'admin', 'protocol'));

        return response()->json(DB::table('protocol_audit')->orderByDesc('id')->limit(1000)->get()->map(fn ($a) => ['id' => (string) $a->id, 'at' => $a->at, 'userId' => $a->user_id === null ? null : (string) $a->user_id, 'action' => $a->action, 'detail' => (string) $a->detail]));
    }

    // ---- Images ----

    private function uploadOut(object $u): array
    {
        return ['id' => $u->id, 'file' => $u->file, 'name' => $u->name, 'type' => $u->type, 'size' => $u->size, 'by' => $u->by === null ? null : (string) $u->by, 'at' => $u->at];
    }

    public function uploads(): JsonResponse
    {
        $this->need($this->isRole($this->me(), 'admin', 'protocol', 'secretary'));

        return response()->json(DB::table('protocol_uploads')->orderByDesc('at')->get()->map(fn ($u) => $this->uploadOut($u)));
    }

    public function uploadStore(Request $request): JsonResponse
    {
        $u = $this->me();
        $this->need($this->isRole($u, 'admin', 'protocol', 'secretary'), 'Only secretaries, protocol officers and administrators can upload images.');
        preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#', (string) $request->input('data', ''), $m) || $this->fail(400, 'Please upload a PNG or JPEG image.');
        $bytes = (string) base64_decode($m[2]);
        $png = str_starts_with($bytes, "\x89PNG");
        $jpg = str_starts_with($bytes, "\xFF\xD8\xFF");
        $png || $jpg || $this->fail(400, 'This file is not a valid image.');
        strlen($bytes) > 6000000 && $this->fail(400, 'The image is too large (maximum 6 MB).');
        $w = (int) $request->input('width');
        $h = (int) $request->input('height');
        $w > 0 && $w <= 5000 && $h > 0 && $h <= 5000 || $this->fail(400, 'Could not read the image size.');
        $id = Str::lower(Str::random(16));
        $file = "{$id}-{$w}x{$h}.".($png ? 'png' : 'jpg');
        Storage::disk('local')->put("protocol/{$file}", $bytes);
        $rec = ['id' => $id, 'file' => $file, 'name' => mb_substr((string) $request->input('name', 'image'), 0, 120), 'type' => $png ? 'image/png' : 'image/jpeg', 'size' => strlen($bytes), 'by' => $u->id, 'at' => $this->now()];
        DB::table('protocol_uploads')->insert($rec);
        $this->log($u, 'Uploaded image', $rec['name']);

        return response()->json($this->uploadOut((object) $rec));
    }

    // ---- Administration: people are the one list of the TFC system ----

    private function admin(): User
    {
        $u = $this->me();
        $this->need($this->role($u) === 'admin');

        return $u;
    }

    public function users(): JsonResponse
    {
        $this->admin();

        return response()->json(User::query()->orderBy('name')->get()->map(fn ($u) => $this->pubUser($u) + ['createdAt' => $u->created_at?->toIso8601ZuluString()])->all());
    }

    private function applyUser(User $u, Request $request): void
    {
        if ($request->has('name')) {
            $u->name = mb_substr(trim((string) $request->input('name')), 0, 120);
        }
        if ($request->has('title')) {
            $u->job_title = mb_substr(trim((string) $request->input('title')), 0, 120);
        }
        if ($request->filled('role')) {
            in_array($request->input('role'), self::ROLES, true) || $this->fail(400, 'Unknown role.');
            $u->protocol_role = $request->input('role');
        }
        if ($request->filled('departmentId')) {
            $this->deptOf($request->input('departmentId')) || $this->fail(400, 'Please choose a department.');
            $u->department_id = (int) $request->input('departmentId');
        }
        if ($request->has('active')) {
            $u->is_active = (bool) $request->input('active');
        }
        if ($request->filled('username')) {
            $email = strtolower(trim((string) $request->input('username')));
            filter_var($email, FILTER_VALIDATE_EMAIL) || $this->fail(400, 'Enter the email this person signs in with.');
            User::query()->where('email', $email)->where('id', '!=', $u->id ?? 0)->exists() && $this->fail(400, 'That email is already used by someone else.');
            $u->email = $email;
        }
        if ($request->filled('password')) {
            strlen((string) $request->input('password')) >= 8 || $this->fail(400, 'Password must have at least 8 characters.');
            $u->password = (string) $request->input('password');
        }
        $u->name !== '' && $u->name !== null || $this->fail(400, 'Please enter the full name.');
    }

    public function userStore(Request $request): JsonResponse
    {
        $me = $this->admin();
        $request->filled('password') || $this->fail(400, 'Please set an initial password.');
        $u = new User(['is_active' => true]);
        $this->applyUser($u, $request);
        $u->email && $u->department_id || $this->fail(400, 'Email and department are required.');
        $u->save();
        $this->log($me, 'Created user', "{$u->name} ({$u->email}, {$u->protocol_role})");

        return response()->json($this->pubUser($u));
    }

    public function userUpdate(Request $request, string $id): JsonResponse
    {
        $me = $this->admin();
        $u = User::query()->find((int) $id) ?? $this->fail(404, 'User not found');
        if ($u->is($me) && ($request->input('active') === false || ($request->filled('role') && $request->input('role') !== 'admin'))) {
            $this->fail(400, 'You cannot remove your own administrator access.');
        }
        $this->applyUser($u, $request);
        $u->save();
        if (! $u->is_active) {
            DB::table('sessions')->where('user_id', $u->id)->delete();
        }
        $this->log($me, 'Updated user', "{$u->name} ({$u->email}, {$this->role($u)}".($u->is_active ? '' : ', deactivated').')');

        return response()->json($this->pubUser($u));
    }

    private function cleanDept(Request $request): array
    {
        $name = mb_substr(trim((string) $request->input('name', '')), 0, 120);
        $code = mb_substr(strtoupper(trim((string) $request->input('code', ''))), 0, 10);
        $name !== '' && preg_match('/^[A-Z0-9]{1,10}$/', $code) || $this->fail(400, 'Enter a department name and a short code (letters/numbers, e.g. HR).');

        return compact('name', 'code');
    }

    public function departmentStore(Request $request): JsonResponse
    {
        $me = $this->admin();
        $v = $this->cleanDept($request);
        Department::query()->where('code', $v['code'])->exists() && $this->fail(400, 'That code is already used.');
        Department::query()->where('name', $v['name'])->exists() && $this->fail(400, 'That department already exists.');
        $d = Department::query()->create($v);
        $this->log($me, 'Created department', $d->name);

        return response()->json(['id' => (string) $d->id, 'name' => $d->name, 'code' => $d->code]);
    }

    public function departmentUpdate(Request $request, string $id): JsonResponse
    {
        $me = $this->admin();
        $d = $this->deptOf($id) ?? $this->fail(404, 'Department not found');
        $v = $this->cleanDept($request);
        Department::query()->where('code', $v['code'])->where('id', '!=', $d->id)->exists() && $this->fail(400, 'That code is already used.');
        $d->update($v);
        $this->log($me, 'Updated department', $d->name);

        return response()->json(['id' => (string) $d->id, 'name' => $d->name, 'code' => $d->code]);
    }

    public function settingsUpdate(Request $request): JsonResponse
    {
        $me = $this->admin();
        $s = $this->settings();
        foreach (['orgName', 'orgCode', 'motto', 'address', 'phone', 'email', 'footer', 'refFormat'] as $k) {
            if ($request->has($k) && $request->input($k) !== null) {
                $s[$k] = mb_substr(trim((string) $request->input($k)), 0, 500);
            }
        }
        str_contains((string) ($s['refFormat'] ?? ''), '{SEQ}') || $this->fail(400, 'The reference format must contain {SEQ}.');
        if ($request->has('logo') && $request->input('logo') !== null) {
            $logo = (string) $request->input('logo');
            $logo !== '' && ! DB::table('protocol_uploads')->where('file', $logo)->exists() && $this->fail(400, 'Logo image not found. Please upload it again.');
            $s['logo'] = $logo;
        }
        $s['showDemoLogins'] = false;
        DB::table('protocol_settings')->update(['data' => json_encode($s, JSON_UNESCAPED_UNICODE)]);
        $this->log($me, 'Updated organization settings');

        return response()->json($s);
    }
}
