@extends('layouts.app', ['title' => 'Uwajibikaji/Tasks'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold tracking-wide text-brand-600 uppercase">Uwajibikaji/Tasks</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Tasks and deadlines</h1>
            <p class="mt-1 text-sm text-slate-500">Give anyone a task with a deadline, and follow it until it is done. A missed deadline is a warning; at {{ \App\Models\DutyWarning::MAX }} warnings the person can no longer explain.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('duty.warnings') }}" class="btn-light">Warnings{{ $counts['warnings'] ? ' ('.$counts['warnings'].'/'.\App\Models\DutyWarning::MAX.')' : '' }}</a>
            @unless ($lock)<a href="{{ route('duty.create') }}" class="btn-primary">+ Give a task</a>@endunless
        </div>
    </div>

    {{-- Locked: the missed deadlines to explain, or five warnings --}}
    @if ($lock === 'five')
        <div class="mb-5 rounded-xl bg-red-50 px-5 py-4 text-sm text-red-900 ring-1 ring-red-200">
            <p class="font-semibold">You have {{ $counts['warnings'] }} warnings — the limit is {{ \App\Models\DutyWarning::MAX }}.</p>
            <p class="mt-1">You can no longer explain missed deadlines, and you cannot hand in work or give tasks here until the administrator clears your warnings.</p>
        </div>
    @endif
    @foreach ($myOpenWarnings as $w)
        <form method="POST" action="{{ route('duty.explain', $w) }}" class="mb-5 space-y-3 rounded-xl bg-red-50 px-5 py-4 ring-1 ring-red-200">@csrf
            <p class="text-sm text-red-900"><span class="font-semibold">You missed the deadline of “{{ $w->task->title }}”</span> ({{ $w->due_at->timezone(\App\Models\DutyTask::TZ)->format('d M Y, H:i') }}).
                Warning {{ $w->number }} of {{ \App\Models\DutyWarning::MAX }}. Explain why — until you do, you cannot hand in work or give tasks here.</p>
            <textarea name="explanation" rows="3" class="field" minlength="10" maxlength="3000" placeholder="Why was the work not handed in on time?" required></textarea>
            @error('explanation')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button class="btn-primary">Send my explanation</button>
        </form>
    @endforeach

    {{-- Explanations waiting for my decision --}}
    @if ($toDecide->isNotEmpty())
        <div class="card mb-5 p-5">
            <p class="font-semibold text-slate-900">Explanations waiting for your decision</p>
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($toDecide as $w)
                    <li class="py-3 text-sm"><a href="{{ route('duty.show', $w->task) }}" class="font-medium text-brand-700 hover:underline">{{ $w->user->name }} — {{ $w->task->title }}</a>
                        <p class="mt-1 line-clamp-2 text-slate-600">“{{ $w->explanation }}”</p></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach (['to_me' => 'Given to me, not done', 'by_me' => 'I gave, not done', 'to_approve' => 'Handed in, for me to approve', 'warnings' => 'My warnings'] as $key => $label)
            <div class="card p-4"><p class="text-xs text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold {{ in_array($key, ['warnings', 'to_approve'], true) && $counts[$key] ? 'text-red-600' : 'text-slate-900' }}">{{ $counts[$key] }}{{ $key === 'warnings' ? '/'.\App\Models\DutyWarning::MAX : '' }}</p></div>
        @endforeach
    </div>

    <nav class="mb-4 flex flex-wrap gap-1 text-sm">
        @foreach (array_filter(['to_me' => 'Given to me', 'by_me' => 'I gave', 'done' => 'Done & cancelled', 'all' => auth()->user()->is_admin ? 'Everyone (administrator)' : null]) as $key => $label)
            <a href="{{ route('duty.index', ['show' => $key]) }}" class="rounded-lg px-3 py-1.5 {{ $show === $key ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="card overflow-hidden">
        @if ($tasks->isEmpty())
            <div class="p-10 text-center"><p class="font-medium text-slate-700">Nothing here.</p></div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($tasks as $task)
                    <li>
                        <a href="{{ route('duty.show', $task) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-4 hover:bg-slate-50">
                            <span class="w-16 text-xs font-semibold text-slate-400">{{ $task->number() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-slate-900">{{ $task->title }}</span>
                                <span class="block text-xs text-slate-500">{{ $task->giver?->name }} → {{ $task->assignee?->name }} · due @include('duty._due')</span>
                            </span>
                            <span>@include('duty._status')</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <div class="mt-4">{{ $tasks->links() }}</div>
@endsection
