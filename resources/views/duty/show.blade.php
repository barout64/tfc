@extends('layouts.app', ['title' => $task->number()])

@section('content')
    @php($tz = \App\Models\DutyTask::TZ)
    @php($max = \App\Models\DutyWarning::MAX)
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('duty.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Uwajibikaji</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-400">{{ $task->number() }} · {{ $task->giver?->name }} → {{ $task->assignee?->name }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $task->title }}</h1>
                <p class="mt-1 text-sm">Due @include('duty._due')</p>
            </div>
            <div class="pt-1">@include('duty._status')</div>
        </div>

        @if ($lock === 'five')
            <p class="mt-5 rounded-xl bg-red-50 px-5 py-4 text-sm text-red-900 ring-1 ring-red-200"><span class="font-semibold">You have {{ $warningCount }} warnings.</span> You cannot hand in work here until the administrator clears them.</p>
        @elseif ($lock === 'explain')
            <p class="mt-5 rounded-xl bg-red-50 px-5 py-4 text-sm text-red-900 ring-1 ring-red-200"><span class="font-semibold">You missed a deadline.</span> Explain why (below, or on the <a href="{{ route('duty.index') }}" class="underline">Uwajibikaji page</a>) — then you can work again.</p>
        @elseif ($doer && $task->status === 'returned')
            <p class="mt-5 rounded-xl bg-rose-50 px-5 py-4 text-sm text-rose-900 ring-1 ring-rose-200"><span class="font-semibold">{{ $task->giver?->name }} sent this back.</span> See what must change below, then hand it in again.</p>
        @elseif ($leader && $task->status === 'submitted')
            <p class="mt-5 rounded-xl bg-violet-50 px-5 py-4 text-sm text-violet-900 ring-1 ring-violet-200"><span class="font-semibold">{{ $task->assignee?->name }} handed in the work.</span> Approve it or send it back.</p>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <article class="card p-5">
                    <p class="text-xs text-slate-400"><span class="font-medium text-slate-600">{{ $task->giver?->name }}</span> gave this {{ $task->created_at->timezone($tz)->format('d M Y, H:i') }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ $task->details ?: 'No details.' }}</p>
                </article>

                {{-- Missed deadlines on this task: the explanation and the decision --}}
                @foreach ($task->warnings as $w)
                    <article class="card p-5 ring-red-200">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-red-700">Missed deadline {{ $w->due_at->timezone($tz)->format('d M Y, H:i') }} · warning {{ $w->number }} of {{ $max }}{{ $w->final ? ' (final)' : '' }}</p>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $w->counts() ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600' }}">{{ \App\Models\DutyWarning::STATUSES[$w->status] }}</span>
                        </div>
                        @if ($w->explanation)
                            <p class="mt-3 text-xs text-slate-400">{{ $task->assignee?->name }} explained {{ $w->explained_at?->timezone($tz)->format('d M H:i') }}</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-800">“{{ $w->explanation }}”</p>
                        @elseif ($w->final)
                            <p class="mt-2 text-sm text-slate-600">The fifth warning: it cannot be explained. Only the administrator can clear warnings.</p>
                        @endif
                        @if ($w->decided_at)
                            <p class="mt-3 text-sm text-slate-700"><span class="font-medium">{{ $w->decider?->name }}</span> {{ $w->decision === 'accepted' ? 'accepted' : 'rejected' }} the explanation; the warning {{ $w->status === 'removed' ? 'was struck off' : 'stands' }}.@if ($w->decision_note) “{{ $w->decision_note }}”@endif</p>
                        @endif
                        @if ($w->cleared_at)
                            <p class="mt-2 text-sm text-slate-700">Cleared by {{ $w->clearer?->name ?? 'the administrator' }}: “{{ $w->clear_reason }}”</p>
                        @endif

                        @if ($doer && $w->status === 'explain')
                            <form method="POST" action="{{ route('duty.explain', $w) }}" class="mt-4 space-y-2">@csrf
                                <label class="label" for="explanation-{{ $w->id }}">Why was the work not handed in on time?</label>
                                <textarea id="explanation-{{ $w->id }}" name="explanation" rows="3" class="field" minlength="10" maxlength="3000" required></textarea>
                                @error('explanation')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                                <button class="btn-primary">Send my explanation</button>
                            </form>
                        @elseif ($leader && $w->status === 'review')
                            <form method="POST" action="{{ route('duty.decide', $w) }}" class="mt-4 space-y-3 rounded-xl bg-slate-50 p-4">@csrf
                                <p class="text-sm font-semibold text-slate-900">Your decision</p>
                                <div class="grid gap-3 sm:grid-cols-2 text-sm">
                                    <fieldset class="space-y-1"><legend class="label">The explanation</legend>
                                        <label class="flex items-center gap-2"><input type="radio" name="decision" value="accepted" required> Accept</label>
                                        <label class="flex items-center gap-2"><input type="radio" name="decision" value="rejected"> Reject</label>
                                    </fieldset>
                                    <fieldset class="space-y-1"><legend class="label">The warning</legend>
                                        <label class="flex items-center gap-2"><input type="radio" name="warning" value="remove" required> Strike it off (does not count)</label>
                                        <label class="flex items-center gap-2"><input type="radio" name="warning" value="keep"> Keep it ({{ $task->assignee?->name }} has {{ $warningCount }} of {{ $max }})</label>
                                    </fieldset>
                                </div>
                                <textarea name="note" rows="2" class="field" maxlength="2000" placeholder="A word to {{ str($task->assignee?->name)->before(' ') }} (optional)"></textarea>
                                <button class="btn-primary">Save the decision</button>
                            </form>
                        @endif
                    </article>
                @endforeach

                @foreach ($task->messages as $m)
                    @if ($m->kind === 'event')
                        <p class="px-2 text-center text-xs text-slate-400">{{ $m->body }} · {{ $m->created_at->timezone($tz)->format('d M H:i') }}</p>
                    @else
                        <article class="card p-5 {{ $m->kind === 'submission' ? 'ring-violet-300' : '' }}">
                            <p class="text-xs text-slate-400"><span class="font-medium text-slate-600">{{ $m->user?->name ?? 'Someone' }}</span>{{ $m->kind === 'submission' ? ' handed in the work' : '' }} · {{ $m->created_at->timezone($tz)->format('d M Y, H:i') }}</p>
                            @if ($m->body !== '')<p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ $m->body }}</p>@endif
                            @if ($m->attachment_path)
                                <a href="{{ route('duty.file', $m) }}" target="_blank" class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-brand-700">📎 {{ $m->attachment_name }}</a>
                            @endif
                        </article>
                    @endif
                @endforeach

                @if (($doer && ! $lock) || $leader)
                    <form method="POST" action="{{ route('duty.reply', $task) }}" enctype="multipart/form-data" class="card space-y-3 p-5">
                        @csrf
                        <label class="label" for="body">{{ $doer ? 'A question or a word to '.str($task->giver?->name)->before(' ') : 'A word to '.str($task->assignee?->name)->before(' ') }}</label>
                        <textarea class="field" id="body" name="body" rows="3" maxlength="5000"></textarea>
                        @error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <input class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm" name="file" type="file" aria-label="File">
                            <button class="btn-light">Send</button>
                        </div>
                    </form>
                @endif
            </div>

            <aside class="space-y-4">
                <div class="card space-y-3 p-5 text-sm">
                    <div><p class="text-xs text-slate-400">Given by</p><p class="font-medium text-slate-800">{{ $task->giver?->name }}</p></div>
                    <div><p class="text-xs text-slate-400">To</p><p class="font-medium text-slate-800">{{ $task->assignee?->name }}</p>
                        <p class="text-xs {{ $warningCount ? 'text-red-600' : 'text-slate-500' }}">{{ $warningCount }} of {{ $max }} warnings</p></div>
                    <div><p class="text-xs text-slate-400">Deadline</p><p class="text-slate-800">{{ $task->dueLabel() }}</p></div>
                    <div><p class="text-xs text-slate-400">Seen</p><p class="text-slate-800">{{ $task->seen_at?->timezone($tz)->format('d M Y, H:i') ?? 'Not yet' }}</p></div>
                    @if ($task->submitted_at)<div><p class="text-xs text-slate-400">Handed in</p><p class="text-slate-800">{{ $task->submitted_at->timezone($tz)->format('d M Y, H:i') }}</p></div>@endif
                    @if ($task->approved_at)<div><p class="text-xs text-slate-400">Approved</p><p class="text-slate-800">{{ $task->approved_at->timezone($tz)->format('d M Y, H:i') }}</p></div>@endif
                </div>

                {{-- The person doing it --}}
                @if ($doer && ! $lock && $task->isToDo())
                    <div class="card space-y-4 p-5">
                        <p class="font-semibold text-slate-900">Your work</p>
                        <form method="POST" action="{{ route('duty.status', $task) }}" class="flex gap-2">@csrf
                            <button name="status" value="on_it" class="btn-light flex-1" @disabled($task->status === 'on_it')>I am on it</button>
                            <button name="status" value="stuck" class="btn-light flex-1" @disabled($task->status === 'stuck')>I need help</button>
                        </form>
                        <form method="POST" action="{{ route('duty.submit', $task) }}" enctype="multipart/form-data" class="space-y-2">@csrf
                            <label class="label" for="submit-body">Hand in the work</label>
                            <input class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm" name="file" type="file" aria-label="The work">
                            <textarea id="submit-body" name="body" rows="2" class="field" maxlength="5000" placeholder="What you did (optional with a file)"></textarea>
                            @error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            <button class="btn-primary w-full">Hand in — send for approval</button>
                        </form>
                    </div>
                @endif

                {{-- The one who gave it (and administrators) --}}
                @if ($leader && $task->isActive())
                    <div class="card space-y-4 p-5">
                        <p class="font-semibold text-slate-900">{{ $task->assigned_by === auth()->id() ? 'You gave this task' : 'Administrator' }}</p>
                        @if ($task->status === 'submitted')
                            <form method="POST" action="{{ route('duty.review', $task) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn-primary w-full">Approve — the work is done</button></form>
                            <form method="POST" action="{{ route('duty.review', $task) }}" class="space-y-2">@csrf<input type="hidden" name="decision" value="return">
                                <textarea name="note" rows="2" class="field" maxlength="2000" placeholder="What must change" required></textarea>
                                <label class="label" for="return-due">New deadline{{ $task->due_at->isPast() ? '' : ' (optional)' }}</label>
                                <input id="return-due" name="due_at" type="datetime-local" class="field" min="{{ now($tz)->format('Y-m-d\TH:i') }}" @required($task->due_at->isPast())>
                                @error('due_at')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                                <button class="btn-light w-full">Send back</button>
                            </form>
                        @endif
                        @if ($task->isToDo())
                            <form method="POST" action="{{ route('duty.deadline', $task) }}" class="space-y-2">@csrf
                                <label class="label" for="new-due">New deadline</label>
                                <input id="new-due" name="due_at" type="datetime-local" class="field" min="{{ now($tz)->format('Y-m-d\TH:i') }}" required>
                                @error('due_at')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                                <p class="text-xs text-slate-500">A warning already given for a missed deadline stays.</p>
                                <button class="btn-light w-full">Change the deadline</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('duty.cancel', $task) }}" class="space-y-2 border-t border-slate-100 pt-4">@csrf
                            <input name="note" class="field" maxlength="1000" placeholder="Why cancel? (required)" required>
                            <button class="btn-light w-full text-red-700">Cancel the task</button>
                        </form>
                    </div>
                @endif
            </aside>
        </div>
    </div>
@endsection
