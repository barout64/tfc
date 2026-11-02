<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\DutyMessage;
use App\Models\DutyTask;
use App\Models\DutyWarning;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uwajibikaji (accountability). Anyone gives anyone a task with a deadline. The person sees it, works on it and hands
 * it in; the one who gave it approves it or sends it back, and may set a new deadline. A deadline that passes without
 * the work is a warning: the person is locked (may only read and explain) until they explain why; the one who gave
 * the task accepts or rejects the explanation and keeps the warning or strikes it off. At five warnings the person can
 * no longer explain and stays locked until the administrator clears them.
 */
class DutyController extends Controller
{
    private const FILE_RULES = ['nullable', 'file', 'max:7168', 'mimes:jpg,jpeg,png,webp,heic,pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt,zip'];

    public function index(Request $request): View
    {
        $me = $request->user();
        $show = in_array($request->query('show'), ['to_me', 'by_me', 'done', 'all'], true) ? $request->query('show') : 'to_me';
        if ($show === 'all' && ! $me->is_admin) {
            $show = 'to_me';
        }

        $tasks = DutyTask::query()->visibleTo($me)->with(['giver', 'assignee'])
            ->when($show === 'to_me', fn ($q) => $q->where('assigned_to', $me->id)->whereIn('status', DutyTask::ACTIVE))
            ->when($show === 'by_me', fn ($q) => $q->where('assigned_by', $me->id)->whereIn('status', DutyTask::ACTIVE))
            ->when($show === 'done', fn ($q) => $q->whereNotIn('status', DutyTask::ACTIVE))
            ->orderByRaw("case when status in ('".implode("','", DutyTask::ACTIVE)."') then 0 else 1 end")
            ->orderByRaw("case when status in ('".implode("','", DutyTask::ACTIVE)."') then due_at end asc")
            ->orderByDesc('updated_at')
            ->paginate(25)->withQueryString();

        return view('duty.index', [
            'tasks' => $tasks, 'show' => $show,
            'counts' => [
                'to_me' => DutyTask::query()->where('assigned_to', $me->id)->whereIn('status', DutyTask::ACTIVE)->count(),
                'by_me' => DutyTask::query()->where('assigned_by', $me->id)->whereIn('status', DutyTask::ACTIVE)->count(),
                'to_approve' => DutyTask::query()->where('assigned_by', $me->id)->where('status', 'submitted')->count(),
                'warnings' => DutyWarning::countFor($me),
            ],
            'toDecide' => $this->toDecide($me)->with(['user', 'task'])->get(),
            'myOpenWarnings' => DutyWarning::query()->where('user_id', $me->id)->where('status', 'explain')->with('task')->get(),
            'lock' => DutyWarning::lockFor($me),
        ]);
    }

    public function create(Request $request): View
    {
        $this->guardLock($request->user());

        return view('duty.create', [
            'people' => User::query()->where('is_active', true)->whereKeyNot($request->user()->id)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();
        $this->guardLock($me);
        $data = $request->validate([
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true), Rule::notIn([$me->id])],
            'title' => ['required', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'file' => self::FILE_RULES,
        ], ['assigned_to.not_in' => 'Give the task to someone else.']);
        $due = $this->parseDeadline($data['due_at']);

        $task = DB::transaction(function () use ($request, $data, $me, $due) {
            $task = DutyTask::query()->create(['assigned_by' => $me->id, 'assigned_to' => $data['assigned_to'], 'title' => $data['title'],
                'details' => $data['details'] ?? null, 'due_at' => $due, 'status' => 'new']);
            if ($request->hasFile('file')) {
                $this->storeFile($task->messages()->create(['user_id' => $me->id, 'kind' => 'reply', 'body' => 'Attached with the task.']), $request->file('file'));
            }

            return $task;
        });
        Activity::record('duty_assigned', null, $task->number().' '.$task->title.' → '.$task->assignee->name);

        return redirect()->route('duty.show', $task)->with('status', "{$task->number()} given to {$task->assignee->name}, due {$task->dueLabel()}.");
    }

    public function show(Request $request, DutyTask $task): View
    {
        $me = $request->user();
        abort_unless($task->canSee($me), 404);

        // The person opens it for the first time: "Seen", and the one who gave it sees when
        if ($task->assigned_to === $me->id && $task->status === 'new') {
            $task->update(['status' => 'seen', 'seen_at' => now()]);
            $task->note("{$me->name} saw the task.", $me);
        }

        return view('duty.show', [
            'task' => $task->load(['giver', 'assignee', 'messages.user', 'warnings.decider']),
            'doer' => $task->assigned_to === $me->id,
            'leader' => $task->canLead($me),
            'lock' => $task->assigned_to === $me->id ? DutyWarning::lockFor($me) : null,
            'warningCount' => DutyWarning::countFor($task->assigned_to),
        ]);
    }

    /** The person: on it / needs help. */
    public function status(Request $request, DutyTask $task): RedirectResponse
    {
        $me = $request->user();
        abort_unless($task->assigned_to === $me->id, 403);
        $this->guardLock($me);
        abort_unless($task->isToDo(), 422, 'This task is not open.');
        $data = $request->validate(['status' => ['required', Rule::in(['on_it', 'stuck'])], 'note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($task, $data, $me) {
            $task->update(['status' => $data['status'], 'started_at' => $task->started_at ?? now()]);
            $task->note($data['status'] === 'on_it' ? "{$me->name} is on it." : "{$me->name} needs help with this.", $me);
            if (filled($data['note'] ?? null)) {
                $task->messages()->create(['user_id' => $me->id, 'kind' => 'reply', 'body' => trim($data['note'])]);
            }
        });
        Activity::record('duty_'.$data['status'], null, $task->number().' '.$task->title);

        return back()->with('status', $task->number().': '.DutyTask::STATUSES[$data['status']].'.');
    }

    /** The person hands in the work (a file, a note, or both): waiting for approval. */
    public function submit(Request $request, DutyTask $task): RedirectResponse
    {
        $me = $request->user();
        abort_unless($task->assigned_to === $me->id, 403);
        $this->guardLock($me);
        abort_unless($task->isToDo(), 422, 'This task is not open.');
        $data = $request->validate(['body' => ['required_without:file', 'nullable', 'string', 'max:5000'], 'file' => self::FILE_RULES],
            ['body.required_without' => 'Attach the work or write what you did.']);
        $late = $task->due_at->isPast();

        DB::transaction(function () use ($request, $task, $data, $me, $late) {
            $message = $task->messages()->create(['user_id' => $me->id, 'kind' => 'submission', 'body' => trim((string) ($data['body'] ?? ''))]);
            if ($request->hasFile('file')) {
                $this->storeFile($message, $request->file('file'));
            }
            $task->update(['status' => 'submitted', 'submitted_at' => now(), 'started_at' => $task->started_at ?? now()]);
            $task->note("{$me->name} handed in the work".($late ? ' (after the deadline)' : '').'. Waiting for approval.', $me);
        });
        Activity::record('duty_submitted', null, $task->number().' '.$task->title);

        return back()->with('status', "Handed in. {$task->giver->name} will approve it or send it back.");
    }

    /** A reply on the task, from the person or the one who gave it (the person, not while locked). */
    public function reply(Request $request, DutyTask $task): RedirectResponse
    {
        $me = $request->user();
        abort_unless($task->canSee($me), 404);
        if ($task->assigned_to === $me->id) {
            $this->guardLock($me);
        }
        $data = $request->validate(['body' => ['required_without:file', 'nullable', 'string', 'max:5000'], 'file' => self::FILE_RULES]);

        DB::transaction(function () use ($request, $task, $data, $me) {
            $message = $task->messages()->create(['user_id' => $me->id, 'kind' => 'reply', 'body' => trim((string) ($data['body'] ?? ''))]);
            if ($request->hasFile('file')) {
                $this->storeFile($message, $request->file('file'));
            }
            $task->touch();
        });

        return back()->with('status', 'Sent.');
    }

    /** The one who gave it: approve the work, or send it back (with what to change, and a new deadline if it has passed). */
    public function review(Request $request, DutyTask $task): RedirectResponse
    {
        $me = $request->user();
        abort_unless($task->canLead($me), 403);
        abort_unless($task->status === 'submitted', 422, 'Nothing handed in to review.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return'])],
            'note' => ['required_if:decision,return', 'nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ], ['note.required_if' => 'Say what must change.']);
        $due = filled($data['due_at'] ?? null) ? $this->parseDeadline($data['due_at']) : null;
        if ($data['decision'] === 'return' && ! $due && $task->due_at->isPast()) {
            throw ValidationException::withMessages(['due_at' => 'The deadline has passed: set a new one.']);
        }

        DB::transaction(function () use ($task, $data, $me, $due) {
            if ($data['decision'] === 'approve') {
                $task->update(['status' => 'approved', 'approved_at' => now()]);
                $task->note("{$me->name} approved the work.", $me);
            } else {
                $task->update(['status' => 'returned'] + ($due ? ['due_at' => $due, 'missed_at' => null] : []));
                $task->note("{$me->name} sent it back".($due ? ', new deadline '.$task->dueLabel() : '').'.', $me);
            }
            if (filled($data['note'] ?? null)) {
                $task->messages()->create(['user_id' => $me->id, 'kind' => 'reply', 'body' => trim($data['note'])]);
            }
        });
        Activity::record($data['decision'] === 'approve' ? 'duty_approved' : 'duty_returned', null, $task->number().' '.$task->title);

        return back()->with('status', $data['decision'] === 'approve' ? 'Approved — done.' : 'Sent back to '.$task->assignee->name.'.');
    }

    /** The one who gave it: a new deadline. A warning already given for the old one stays. */
    public function deadline(Request $request, DutyTask $task): RedirectResponse
    {
        $me = $request->user();
        abort_unless($task->canLead($me), 403);
        abort_unless($task->isToDo(), 422, 'This task is not open.');
        $data = $request->validate(['due_at' => ['required', 'date_format:Y-m-d\TH:i'], 'note' => ['nullable', 'string', 'max:1000']]);
        $due = $this->parseDeadline($data['due_at']);
        $old = $task->dueLabel();

        $task->update(['due_at' => $due, 'missed_at' => null]);
        $task->note("{$me->name} changed the deadline from {$old} to {$task->dueLabel()}.".(filled($data['note'] ?? null) ? ' '.trim($data['note']) : ''), $me);
        Activity::record('duty_deadline', null, $task->number().' → '.$task->dueLabel());

        return back()->with('status', 'New deadline: '.$task->dueLabel().'.');
    }

    public function cancel(Request $request, DutyTask $task): RedirectResponse
    {
        $me = $request->user();
        abort_unless($task->canLead($me), 403);
        abort_unless($task->isActive(), 422);
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']], ['note.required' => 'Say why it is cancelled.']);

        $task->update(['status' => 'cancelled']);
        $task->note("{$me->name} cancelled the task: ".trim($data['note']), $me);
        Activity::record('duty_cancelled', null, $task->number().' '.$task->title);

        return back()->with('status', $task->number().' cancelled.');
    }

    /** The person explains why they missed the deadline: they may work again; the one who gave the task decides. */
    public function explain(Request $request, DutyWarning $warning): RedirectResponse
    {
        $me = $request->user();
        abort_unless($warning->user_id === $me->id, 403);
        if ($warning->status !== 'explain') {
            return back()->with('error', $warning->final ? 'This was the fifth warning: it cannot be explained. Only the administrator can clear warnings.' : 'Already explained.');
        }
        $data = $request->validate(['explanation' => ['required', 'string', 'min:10', 'max:3000']]);

        DB::transaction(function () use ($warning, $data, $me) {
            $warning->update(['status' => 'review', 'explanation' => trim($data['explanation']), 'explained_at' => now()]);
            $warning->task->note("{$me->name} explained why the deadline was missed. Waiting for {$warning->task->giver->name} to decide.", $me);
        });
        Activity::record('duty_explained', null, $warning->task->number().' '.$warning->task->title);

        return back()->with('status', DutyWarning::lockFor($me) ? 'Explanation sent. You still have another missed deadline to explain.' : 'Explanation sent. You can work again.');
    }

    /**
     * The one who gave the task (or an administrator) decides on the explanation: accepted or rejected, and the warning
     * kept or struck off.
     */
    public function decide(Request $request, DutyWarning $warning): RedirectResponse
    {
        $me = $request->user();
        abort_unless($warning->task->canLead($me), 403);
        abort_unless($warning->status === 'review', 422, 'Nothing to decide.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'warning' => ['required', Rule::in(['keep', 'remove'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($warning, $data, $me) {
            $warning->update(['status' => $data['warning'] === 'remove' ? 'removed' : 'kept', 'decision' => $data['decision'],
                'decision_note' => filled($data['note'] ?? null) ? trim($data['note']) : null, 'decided_by' => $me->id, 'decided_at' => now()]);
            $warning->task->note("{$me->name} ".($data['decision'] === 'accepted' ? 'accepted' : 'rejected').' the explanation; the warning '
                .($data['warning'] === 'remove' ? 'is struck off.' : 'stands.'), $me);
        });
        Activity::record('duty_decided', null, $warning->task->number().': '.$data['decision'].', warning '.($data['warning'] === 'remove' ? 'struck off' : 'kept').' for '.$warning->user->name);

        return back()->with('status', 'Decided. '.$warning->user->name.' has '.DutyWarning::countFor($warning->user_id).' of '.DutyWarning::MAX.' warnings.');
    }

    /** Warnings: mine; for an administrator, everyone's, with the reset. */
    public function warnings(Request $request): View
    {
        $me = $request->user();

        return view('duty.warnings', [
            'mine' => DutyWarning::query()->where('user_id', $me->id)->with(['task', 'decider', 'clearer'])->latest('id')->get(),
            'people' => $me->is_admin
                ? User::query()->whereHas('dutyWarnings')->withCount(['dutyWarnings as counting_count' => fn ($q) => $q->counting()])
                    ->withCount(['dutyWarnings as waiting_count' => fn ($q) => $q->where('status', 'explain')])->orderByDesc('counting_count')->orderBy('name')->get()
                : collect(),
            'everyone' => $me->is_admin ? DutyWarning::query()->with(['user', 'task', 'decider', 'clearer'])->latest('id')->limit(100)->get() : collect(),
            'count' => DutyWarning::countFor($me),
        ]);
    }

    /** The administrator clears a person's warnings (with a reason): they can work and explain again. */
    public function clear(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Say why the warnings are cleared.']);

        $cleared = DB::transaction(function () use ($user, $me, $data) {
            $warnings = DutyWarning::query()->where('user_id', $user->id)->counting()->with('task')->get();
            foreach ($warnings as $w) {
                $w->update(['status' => 'cleared', 'cleared_by' => $me->id, 'cleared_at' => now(), 'clear_reason' => trim($data['reason'])]);
                $w->task->note("{$me->name} cleared {$user->name}'s warning: ".trim($data['reason']), $me);
            }

            return $warnings->count();
        });
        Activity::record('duty_cleared', null, "{$cleared} warning(s) of {$user->name}");

        return back()->with('status', "{$cleared} ".str('warning')->plural($cleared)." of {$user->name} cleared.");
    }

    public function file(Request $request, DutyMessage $message): StreamedResponse
    {
        abort_unless($message->task->canSee($request->user()), 404);
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name);
    }

    /** Explanations waiting for $me: on tasks they gave (administrators: all). */
    private function toDecide(User $me)
    {
        return DutyWarning::query()->where('status', 'review')
            ->when(! $me->is_admin, fn ($q) => $q->whereHas('task', fn ($t) => $t->where('assigned_by', $me->id)));
    }

    /** Locked people may only read and explain. */
    private function guardLock(User $me): void
    {
        $lock = DutyWarning::lockFor($me);
        if ($lock) {
            throw new HttpResponseException(redirect()->route('duty.index')->with('error', $lock === 'five'
                ? 'You have '.DutyWarning::MAX.' warnings: you cannot work in Uwajibikaji until the administrator clears them.'
                : 'You missed a deadline. Explain why first — then you can work again.'));
        }
    }

    /** A deadline typed in Tanzania time; it must be in the future. */
    private function parseDeadline(string $value): Carbon
    {
        $due = Carbon::createFromFormat('Y-m-d\TH:i', $value, DutyTask::TZ)->utc();
        if ($due->lte(now())) {
            throw ValidationException::withMessages(['due_at' => 'The deadline must be later than now.']);
        }

        return $due;
    }

    private function storeFile(DutyMessage $message, UploadedFile $file): void
    {
        $name = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()) ?: 'file', -120);
        $message->update(['attachment_path' => $file->store('duty/'.$message->task_id, 'local'), 'attachment_name' => $name]);
    }
}
