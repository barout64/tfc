<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' · ' : '' }}TFC Portal</title>
    <link rel="icon" href="/images/coat-of-arms-sm.png" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-slate-800 antialiased">
@auth
    <header class="bg-brand-900 text-white">
        <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <img src="/images/coat-of-arms-sm.png" alt="" class="h-10 w-auto">
                <span class="leading-tight">
                    <span class="block text-base font-bold tracking-wide">TFC Portal</span>
                    <span class="block text-xs text-brand-200">Tanzania Fertilizer Company</span>
                </span>
            </a>
            <nav class="ml-auto flex flex-wrap items-center gap-1 text-sm">
                <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 hover:bg-brand-800 {{ request()->routeIs('home') ? 'bg-brand-800' : '' }}">Systems</a>
                @php
                    // Support: the open requests; everyone else: their requests waiting for them (an answer, or "does it work now?")
                    $helpdeskWaiting = auth()->user()->isSupport()
                        ? \App\Models\HelpdeskTicket::query()->whereIn('status', ['open', 'in_progress'])->count()
                        : \App\Models\HelpdeskTicket::query()->where('user_id', auth()->id())->whereIn('status', ['waiting', 'resolved'])->count();
                @endphp
                <a href="{{ route('helpdesk.index') }}" class="flex items-center gap-1.5 rounded-lg px-3 py-2 hover:bg-brand-800 {{ request()->routeIs('helpdesk.*') ? 'bg-brand-800' : '' }}">Helpdesk
                    @if ($helpdeskWaiting)<span class="rounded-full bg-harvest-400 px-1.5 text-xs font-bold text-brand-950">{{ $helpdeskWaiting }}</span>@endif</a>
                @php
                    // Uwajibikaji: my tasks still to do, work handed in for me to approve, explanations for me to decide, my missed deadlines to explain
                    $me = auth()->user();
                    $dutyWaiting = \App\Models\DutyTask::query()->where('assigned_to', $me->id)->whereIn('status', \App\Models\DutyTask::TO_DO)->count()
                        + \App\Models\DutyTask::query()->where('assigned_by', $me->id)->where('status', 'submitted')->count()
                        + \App\Models\DutyWarning::query()->where('status', 'review')->whereHas('task', fn ($q) => $q->where('assigned_by', $me->id))->count()
                        + \App\Models\DutyWarning::query()->where('user_id', $me->id)->where('status', 'explain')->count();
                    $dutyLock = \App\Models\DutyWarning::lockFor($me);
                    $dutySoon = \App\Models\DutyTask::query()->where('assigned_to', $me->id)->whereIn('status', \App\Models\DutyTask::TO_DO)
                        ->whereBetween('due_at', [now(), now()->addDay()])->orderBy('due_at')->limit(3)->get();
                @endphp
                <a href="{{ route('duty.index') }}" class="flex items-center gap-1.5 rounded-lg px-3 py-2 hover:bg-brand-800 {{ request()->routeIs('duty.*') ? 'bg-brand-800' : '' }}">Uwajibikaji/Tasks
                    @if ($dutyWaiting)<span class="rounded-full bg-harvest-400 px-1.5 text-xs font-bold text-brand-950">{{ $dutyWaiting }}</span>@endif</a>
                <a href="{{ route('meetings.dashboard') }}" class="rounded-lg px-3 py-2 hover:bg-brand-800">Meetings</a>
                <a href="{{ route('protocol.index') }}" class="rounded-lg px-3 py-2 hover:bg-brand-800">Protocol</a>
                @can('reports')
                    @php
                        // Admin: how many stations have not reported today; a station's own person: whether today is still missing
                        $reportsWaiting = $me->is_admin
                            ? \App\Models\Station::query()->where('is_active', true)->whereDoesntHave('dailyReports', fn ($q) => $q->whereDate('report_date', now()->toDateString()))->count()
                            : ($me->station_id && ! \App\Models\DailyReport::query()->where('station_id', $me->station_id)->whereDate('report_date', now()->toDateString())->exists() ? 1 : 0);
                    @endphp
                    <a href="{{ route('reports.index') }}" class="flex items-center gap-1.5 rounded-lg px-3 py-2 hover:bg-brand-800 {{ request()->routeIs('reports.*') ? 'bg-brand-800' : '' }}">Daily Report
                        @if ($reportsWaiting)<span class="rounded-full bg-harvest-400 px-1.5 text-xs font-bold text-brand-950">{{ $reportsWaiting }}</span>@endif</a>
                @endcan
                @can('admin')
                    <a href="{{ route('admin.users.index') }}" class="rounded-lg px-3 py-2 hover:bg-brand-800 {{ request()->routeIs('admin.users.*') ? 'bg-brand-800' : '' }}">People &amp; access</a>
                    <a href="{{ route('admin.stations.index') }}" class="rounded-lg px-3 py-2 hover:bg-brand-800 {{ request()->routeIs('admin.stations.*') ? 'bg-brand-800' : '' }}">Stations</a>
                    <a href="{{ route('admin.activity') }}" class="rounded-lg px-3 py-2 hover:bg-brand-800 {{ request()->routeIs('admin.activity') ? 'bg-brand-800' : '' }}">Activity</a>
                @endcan
                <a href="{{ route('password.edit') }}" class="rounded-lg px-3 py-2 hover:bg-brand-800 {{ request()->routeIs('password.*') ? 'bg-brand-800' : '' }}">Password</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-lg px-3 py-2 text-brand-100 hover:bg-brand-800 hover:text-white">Sign out</button>
                </form>
            </nav>
        </div>
    </header>
@endauth

<main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    @auth
        {{-- Uwajibikaji reminders, on every page --}}
        @if ($dutyLock && ! request()->routeIs('duty.index'))
            <a href="{{ route('duty.index') }}" class="mb-4 block rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                <span class="font-semibold">Uwajibikaji/Tasks:</span> {{ $dutyLock === 'five' ? 'you have '.\App\Models\DutyWarning::MAX.' warnings — only the administrator can clear them.' : 'you missed a deadline. Explain why before you can work there again →' }}</a>
        @endif
        @foreach ($dutySoon as $soon)
            <a href="{{ route('duty.show', $soon) }}" class="mb-4 block rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                <span class="font-semibold">Reminder:</span> “{{ $soon->title }}” for {{ $soon->giver?->name }} is due {{ $soon->dueLabel() }} — in {{ $soon->due_at->diffForHumans(null, true) }}.</a>
        @endforeach
    @endauth
    @if (session('status'))
        <p class="mb-6 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800 ring-1 ring-brand-200">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</p>
    @endif
    @yield('content')
</main>

<footer class="pb-8 text-center text-xs text-slate-400">Tanzania Fertilizer Company · TFC Portal</footer>
</body>
</html>
