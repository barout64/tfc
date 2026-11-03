@extends('layouts.app', ['title' => 'Warnings'])

@section('content')
    @php($tz = \App\Models\DutyTask::TZ)
    @php($max = \App\Models\DutyWarning::MAX)
    <a href="{{ route('duty.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Uwajibikaji/Tasks</a>
    <h1 class="mt-2 text-2xl font-bold text-slate-900">Warnings</h1>
    <p class="mt-1 text-sm text-slate-500">Each missed deadline is a warning. The one who gave the task can strike it off after your explanation. At {{ $max }} you can no longer explain; only the administrator can clear warnings.</p>

    @if (auth()->user()->is_admin)
        <div class="card mt-6 overflow-x-auto">
            <p class="px-5 pt-5 font-semibold text-slate-900">Everyone with warnings</p>
            <table class="mt-3 w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                    <tr><th class="px-4 py-3">Person</th><th class="px-4 py-3">Warnings</th><th class="px-4 py-3">Not explained</th><th class="px-4 py-3">Clear (reset)</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($people as $p)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $p->name }}@if ($p->counting_count >= $max)<span class="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Locked</span>@endif</td>
                            <td class="px-4 py-3 {{ $p->counting_count ? 'font-semibold text-red-600' : 'text-slate-500' }}">{{ $p->counting_count }} / {{ $max }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $p->waiting_count ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($p->counting_count)
                                    <form method="POST" action="{{ route('duty.clear', $p) }}" class="flex gap-2">@csrf
                                        <input name="reason" class="field" maxlength="1000" placeholder="Why clear them? (kept on record)" required>
                                        <button class="btn-light whitespace-nowrap">Clear warnings</button>
                                    </form>
                                @else<span class="text-slate-400">—</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Nobody has warnings.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @foreach (array_filter(['Mine — '.$count.' of '.$max => $mine, 'All warnings (latest 100)' => auth()->user()->is_admin ? $everyone : null]) as $heading => $list)
        <div class="card mt-6 overflow-x-auto">
            <p class="px-5 pt-5 font-semibold text-slate-900">{{ $heading }}</p>
            <table class="mt-3 w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                    <tr><th class="px-4 py-3">Deadline missed</th>@if ($list !== $mine)<th class="px-4 py-3">Person</th>@endif<th class="px-4 py-3">Task</th><th class="px-4 py-3">No.</th><th class="px-4 py-3">Now</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($list as $w)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $w->due_at->timezone($tz)->format('d M Y H:i') }}</td>
                            @if ($list !== $mine)<td class="px-4 py-3 font-medium text-slate-800">{{ $w->user?->name }}</td>@endif
                            <td class="px-4 py-3"><a href="{{ route('duty.show', $w->task) }}" class="text-brand-700 hover:underline">{{ $w->task->title }}</a></td>
                            <td class="px-4 py-3 text-slate-600">{{ $w->number }}{{ $w->final ? ' (final)' : '' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $w->counts() ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600' }}">{{ \App\Models\DutyWarning::STATUSES[$w->status] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No warnings.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
@endsection
