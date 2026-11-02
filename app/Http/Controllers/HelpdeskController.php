<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\HelpdeskMessage;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TFC Helpdesk: every staff member asks for help and follows their requests; helpdesk support (and administrators)
 * see all requests, take them, answer, and mark them fixed. The person who asked confirms it works, or reopens it.
 */
class HelpdeskController extends Controller
{
    private const FILE_RULES = ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,heic,pdf,doc,docx,xls,xlsx,txt'];

    public function index(Request $request): View
    {
        $me = $request->user();
        $support = $me->isSupport();
        $show = $support ? (in_array($request->query('show'), ['open', 'mine', 'done', 'all'], true) ? $request->query('show') : 'open') : 'all';

        $tickets = HelpdeskTicket::query()->visibleTo($me)->with(['requester', 'assignee'])
            ->when($show === 'open', fn ($q) => $q->whereIn('status', HelpdeskTicket::OPEN))
            ->when($show === 'mine', fn ($q) => $q->where('assigned_to', $me->id)->whereIn('status', HelpdeskTicket::OPEN))
            ->when($show === 'done', fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
            // the most pressing first: open before done, urgent before the rest, oldest waiting first
            ->orderByRaw("case when status in ('open','in_progress','waiting') then 0 else 1 end")
            ->orderByRaw("case priority when 'urgent' then 0 when 'normal' then 1 else 2 end")
            ->orderByRaw("case when status in ('open','in_progress','waiting') then created_at end asc")
            ->orderByDesc('updated_at')
            ->paginate(25)->withQueryString();

        return view('helpdesk.index', [
            'tickets' => $tickets, 'show' => $show, 'support' => $support,
            'counts' => $support ? [
                'open' => HelpdeskTicket::query()->whereIn('status', HelpdeskTicket::OPEN)->count(),
                'new' => HelpdeskTicket::query()->where('status', 'open')->count(),
                'mine' => HelpdeskTicket::query()->where('assigned_to', $me->id)->whereIn('status', HelpdeskTicket::OPEN)->count(),
                'urgent' => HelpdeskTicket::query()->whereIn('status', HelpdeskTicket::OPEN)->where('priority', 'urgent')->count(),
            ] : null,
        ]);
    }

    public function create(): View
    {
        return view('helpdesk.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(HelpdeskTicket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(HelpdeskTicket::PRIORITIES))],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'file' => self::FILE_RULES,
        ]);

        $ticket = DB::transaction(function () use ($request, $data) {
            $ticket = HelpdeskTicket::query()->create(['user_id' => $request->user()->id, 'status' => 'open'] + collect($data)->except('file')->all());
            if ($request->hasFile('file')) {
                $this->attach($ticket, $request->file('file'), 'Attached when asking.', $request->user());
            }

            return $ticket;
        });
        Activity::record('helpdesk_asked', 'helpdesk', $ticket->number().' '.$ticket->subject);

        return redirect()->route('helpdesk.show', $ticket)->with('status', "Your request {$ticket->number()} is sent to helpdesk support. You can follow it here.");
    }

    public function show(Request $request, HelpdeskTicket $ticket): View
    {
        $this->authorizeSee($request->user(), $ticket);

        return view('helpdesk.show', [
            'ticket' => $ticket->load(['requester', 'assignee', 'messages.user']),
            'support' => $request->user()->isSupport(),
            'mine' => $ticket->user_id === $request->user()->id,
            'supportPeople' => $request->user()->isSupport()
                ? User::query()->where('is_active', true)->where(fn ($q) => $q->where('is_support', true)->orWhere('is_admin', true))->orderBy('name')->pluck('name', 'id')
                : collect(),
        ]);
    }

    /** A reply from the person who asked or from support, with an optional photo or file. */
    public function reply(Request $request, HelpdeskTicket $ticket): RedirectResponse
    {
        $me = $request->user();
        $this->authorizeSee($me, $ticket);
        $data = $request->validate(['body' => ['required_without:file', 'nullable', 'string', 'max:5000'], 'file' => self::FILE_RULES]);

        DB::transaction(function () use ($request, $ticket, $data, $me) {
            $message = $ticket->messages()->create(['user_id' => $me->id, 'kind' => 'reply', 'body' => trim((string) ($data['body'] ?? ''))]);
            if ($request->hasFile('file')) {
                $this->storeFile($message, $request->file('file'));
            }
            // The person answers what support asked, or says it is still not working: back to support
            if (! $me->isSupport() || $ticket->user_id === $me->id) {
                if (in_array($ticket->status, ['waiting', 'resolved', 'closed'], true)) {
                    $ticket->update(['status' => $ticket->assigned_to ? 'in_progress' : 'open', 'resolved_at' => null, 'closed_at' => null]);
                }
            }
            $ticket->touch();
        });

        return redirect()->route('helpdesk.show', $ticket)->with('status', 'Reply sent.');
    }

    /** Support takes the request (or gives it to another support person). */
    public function assign(Request $request, HelpdeskTicket $ticket): RedirectResponse
    {
        $me = $request->user();
        $data = $request->validate(['assigned_to' => ['nullable', 'integer']]);
        $to = isset($data['assigned_to']) ? User::query()->findOrFail($data['assigned_to']) : $me;
        abort_unless($to->isSupport(), 422, 'That person is not helpdesk support.');

        $ticket->update(['assigned_to' => $to->id, 'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status]);
        $ticket->note($to->is($me) ? "{$me->name} is taking care of this." : "{$me->name} gave this to {$to->name}.");
        Activity::record('helpdesk_taken', 'helpdesk', $ticket->number().' → '.$to->name);

        return back()->with('status', $to->is($me) ? "{$ticket->number()} is yours." : "{$ticket->number()} given to {$to->name}.");
    }

    /** Support: being fixed / waiting for the person / fixed. */
    public function status(Request $request, HelpdeskTicket $ticket): RedirectResponse
    {
        $me = $request->user();
        $data = $request->validate(['status' => ['required', Rule::in(['in_progress', 'waiting', 'resolved'])], 'note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($ticket, $data, $me) {
            $ticket->update([
                'status' => $data['status'],
                'assigned_to' => $ticket->assigned_to ?? $me->id,
                'resolved_at' => $data['status'] === 'resolved' ? now() : null,
                'closed_at' => null,
            ]);
            $ticket->note(match ($data['status']) {
                'resolved' => "{$me->name} marked this as fixed.",
                'waiting' => "{$me->name} is waiting for an answer from {$ticket->requester->name}.",
                default => "{$me->name} is working on this.",
            });
            if (filled($data['note'] ?? null)) {
                $ticket->messages()->create(['user_id' => $me->id, 'kind' => 'reply', 'body' => trim($data['note'])]);
            }
        });
        Activity::record('helpdesk_'.$data['status'], 'helpdesk', $ticket->number().' '.$ticket->subject);

        return back()->with('status', $ticket->number().': '.HelpdeskTicket::STATUSES[$data['status']].'.');
    }

    /** The person who asked: it works now (closed), or it still does not (reopened). */
    public function confirm(Request $request, HelpdeskTicket $ticket): RedirectResponse
    {
        $me = $request->user();
        abort_unless($ticket->user_id === $me->id || $me->isSupport(), 403);
        $works = $request->boolean('works');
        abort_if($works && $ticket->status !== 'resolved', 422);

        if ($works) {
            $ticket->update(['status' => 'closed', 'closed_at' => now()]);
            $ticket->note("{$me->name} confirmed it works. Closed.");
        } else {
            $ticket->update(['status' => $ticket->assigned_to ? 'in_progress' : 'open', 'resolved_at' => null, 'closed_at' => null]);
            $ticket->note("{$me->name} says it is still not working. Opened again.");
        }
        Activity::record($works ? 'helpdesk_closed' : 'helpdesk_reopened', 'helpdesk', $ticket->number());

        return back()->with('status', $works ? 'Thank you — closed.' : 'Opened again; support will look at it.');
    }

    public function file(Request $request, HelpdeskMessage $message): StreamedResponse
    {
        $this->authorizeSee($request->user(), $message->ticket);
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name);
    }

    private function authorizeSee(User $user, HelpdeskTicket $ticket): void
    {
        abort_unless($ticket->user_id === $user->id || $user->isSupport(), 404);
    }

    private function attach(HelpdeskTicket $ticket, UploadedFile $file, string $body, User $by): void
    {
        $this->storeFile($ticket->messages()->create(['user_id' => $by->id, 'kind' => 'reply', 'body' => $body]), $file);
    }

    private function storeFile(HelpdeskMessage $message, UploadedFile $file): void
    {
        $name = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()) ?: 'file', -120);
        $message->update(['attachment_path' => $file->store('helpdesk/'.$message->ticket_id, 'local'), 'attachment_name' => $name]);
    }
}
