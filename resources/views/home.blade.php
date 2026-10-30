@extends('layouts.app')

@section('content')
    @php
        $hour = (int) now('Africa/Dar_es_Salaam')->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $icons = [
            'meeting' => 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 10h2m4 0h2m-8 4h2',
            'protocol' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6M8 13h8M8 17h5',
            'kilimo' => 'M12 22V12m0 0C12 7 8 4 3 4c0 5 4 8 9 8Zm0 0c0-5 4-8 9-8 0 5-4 8-9 8Zm-6 10h12',
        ];
    @endphp

    <div class="mb-8">
        <p class="text-sm font-semibold tracking-wide text-brand-600 uppercase">TFC systems</p>
        <h1 class="mt-1 text-3xl font-extrabold text-slate-900">{{ $greeting }}, {{ str(auth()->user()->name)->before(' ') }}</h1>
        <p class="mt-2 text-slate-500">Choose the system you want to work in. You are signed in to it straight away.</p>
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        @foreach ($systems as $s)
            <article class="card flex flex-col p-6 {{ $s['link'] ? '' : 'opacity-70' }}">
                <div class="flex items-start justify-between">
                    <span class="grid size-12 place-items-center rounded-xl {{ $s['link'] ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-400' }}">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons[$s['id']] ?? $icons['protocol'] }}"/></svg>
                    </span>
                    @if ($s['link'])
                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">Access</span>
                    @else
                        <span class="flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 11V7a5 5 0 0 1 10 0v4M5 11h14v10H5z"/></svg>No access
                        </span>
                    @endif
                </div>
                <h2 class="mt-5 text-xl font-bold text-slate-900">{{ $s['name'] }}</h2>
                <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-500">{{ $s['about'] }}</p>
                @if ($s['link'])
                    <p class="mt-4 text-xs text-slate-400">You enter as <span class="font-medium text-slate-600">{{ $s['link']->remote_name }}</span></p>
                    <a href="{{ route('open', $s['id']) }}" class="btn-primary mt-4 w-full py-2.5">Open {{ $s['name'] }} →</a>
                @else
                    <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2.5 text-center text-xs text-slate-500">Ask the TFC administrator for access</p>
                @endif
            </article>
        @endforeach
    </div>
@endsection
