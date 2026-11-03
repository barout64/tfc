@extends('layouts.app')

@section('content')
    @php
        $hour = (int) now('Africa/Dar_es_Salaam')->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        $cards = [
            [
                'id' => 'helpdesk', 'name' => 'Helpdesk', 'open' => true,
                'icon' => 'M3 18v-6a9 9 0 0 1 18 0v6M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z',
                'text' => 'Ask for help with anything not working.',
                'url' => route('helpdesk.index'),
                'stat' => $helpdesk['mine_open'] || $helpdesk['assigned_to_me']
                    ? ($support ? $helpdesk['assigned_to_me'].' assigned to me' : $helpdesk['mine_open'].' open request(s)')
                    : 'Nothing open',
                'alert' => $support ? $helpdesk['urgent'] > 0 : false,
            ],
            [
                'id' => 'duty', 'name' => 'Uwajibikaji/Tasks', 'open' => true,
                'icon' => 'M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9',
                'text' => 'Give or receive a task with a deadline.',
                'url' => route('duty.index'),
                'stat' => $duty['to_me'].' to do · '.$duty['to_approve'].' to approve',
                'alert' => $duty['warnings'] > 0 || $duty['lock'],
            ],
        ];
        if ($canReport) {
            $cards[] = [
                'id' => 'reports', 'name' => 'Daily Report', 'open' => true,
                'icon' => 'M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z',
                'text' => 'Taarifa ya Mauzo ya Kila Siku.',
                'url' => route('reports.index'),
                'stat' => $reports['admin']
                    ? ($reports['missing_today'] > 0 ? $reports['missing_today'].' of '.$reports['stations'].' stations have not reported today' : 'All stations reported today')
                    : ($reports['reported_today'] ? 'Today: submitted' : 'Today: not yet submitted'),
                'alert' => $reports['admin'] ? $reports['missing_today'] > 0 : ! $reports['reported_today'],
            ];
        }
        foreach ($systems as $s) {
            $cards[] = [
                'id' => $s['id'], 'name' => $s['name'], 'open' => (bool) $s['link'],
                'icon' => ['meeting' => 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 10h2m4 0h2m-8 4h2', 'protocol' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6M8 13h8M8 17h5'][$s['id']] ?? 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6M8 13h8M8 17h5',
                'text' => $s['about'],
                'url' => $s['link'] ? route('open', $s['id']) : null,
                'stat' => $s['link'] ? null : 'No access',
                'alert' => false,
            ];
        }
    @endphp

    <div class="mb-8">
        <p class="text-sm font-semibold tracking-wide text-brand-600 uppercase">TFC Portal</p>
        <h1 class="mt-1 text-3xl font-extrabold text-slate-900">{{ $greeting }}, {{ str(auth()->user()->name)->before(' ') }}</h1>
        <p class="mt-2 text-slate-500">Every TFC system, in one place.</p>
    </div>

    @if ($duty['lock'])
        <div class="mb-6 rounded-xl bg-red-50 px-5 py-4 text-sm text-red-900 ring-1 ring-red-200">
            {{ $duty['lock'] === 'five' ? 'You have '.\App\Models\DutyWarning::MAX.' warnings: you cannot work in Uwajibikaji/Tasks until the administrator clears them.' : 'You missed a deadline in Uwajibikaji/Tasks. Explain why before you can work again.' }}
            <a href="{{ route('duty.index') }}" class="ml-1 font-semibold underline">Go to Uwajibikaji/Tasks →</a>
        </div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $c)
            <div class="card flex flex-col p-6 {{ $c['open'] ? '' : 'opacity-60' }}">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $c['open'] ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-400' }}">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $c['icon'] }}"/></svg>
                    </span>
                    <h2 class="text-lg font-bold text-slate-900">{{ $c['name'] }}</h2>
                </div>
                <p class="mt-3 flex-1 text-sm text-slate-500">{{ $c['text'] }}</p>
                @if ($c['stat'])
                    <p class="mt-3 text-sm font-medium {{ $c['alert'] ? 'text-red-600' : 'text-slate-700' }}">{{ $c['stat'] }}</p>
                @endif
                <div class="mt-5">
                    @if ($c['open'])
                        <a href="{{ $c['url'] }}" class="btn-primary w-full">Open {{ $c['name'] }} →</a>
                    @else
                        <span class="btn-light w-full cursor-not-allowed opacity-70">No access</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection
