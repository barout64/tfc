<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TFC Portal</title>
    <link rel="icon" href="/images/coat-of-arms-sm.png" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white text-slate-800 antialiased">
    @php
        $portals = [
            ['icon' => 'M3 18v-6a9 9 0 0 1 18 0v6M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z', 'name' => 'Helpdesk', 'text' => 'Ask for help with anything not working, and follow the answer until it is fixed.'],
            ['icon' => 'M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9', 'name' => 'Uwajibikaji/Tasks', 'text' => 'Give or receive a task with a deadline, and keep track of it until it is done.'],
            ['icon' => 'M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z', 'name' => 'Daily Report', 'text' => 'Taarifa ya Mauzo ya Kila Siku — every station reports what it sold, every day.'],
            ['icon' => 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 10h2m4 0h2m-8 4h2', 'name' => 'Meetings', 'text' => 'Agendas, minutes, decisions and action items for every TFC meeting.'],
            ['icon' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6M8 13h8M8 17h5', 'name' => 'Protocol', 'text' => 'Official letters and documents, drafted, reviewed and approved.'],
        ];
    @endphp

    <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <img src="/images/coat-of-arms-sm.png" alt="" class="h-10 w-auto">
                <span class="leading-tight">
                    <span class="block font-semibold text-slate-900">TFC Portal</span>
                    <span class="block text-[11px] text-slate-500">Tanzania Fertilizer Company</span>
                </span>
            </a>
            <a href="{{ route('login') }}" class="btn-primary ml-auto">Sign in</a>
        </div>
    </header>

    <section class="relative overflow-hidden bg-brand-950 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(184,134,11,.35),transparent_50%),radial-gradient(circle_at_85%_70%,rgba(255,255,255,.12),transparent_45%)]"></div>
        <div class="relative mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-24">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs ring-1 ring-white/20"><span class="size-1.5 rounded-full bg-harvest-400"></span>TFC Portal</span>
                <h1 class="mt-5 text-4xl leading-tight font-extrabold sm:text-5xl">One portal for<br><span class="text-harvest-400">every TFC system.</span></h1>
                <p class="mt-5 max-w-lg text-lg text-brand-200">Helpdesk, Uwajibikaji/Tasks, Daily Report, Meetings and Protocol — one sign-in, all in one place.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="btn bg-harvest-400 px-5 py-3 text-base font-semibold text-brand-950 hover:bg-harvest-500">Sign in</a>
                </div>
                <p class="mt-6 text-xs text-brand-300">For TFC staff only. Accounts are created by the TFC administrator.</p>
            </div>
            <div class="grid grid-cols-2 gap-4 self-center">
                @foreach ($portals as $p)
                    <div class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/10 backdrop-blur">
                        <svg class="size-6 text-harvest-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $p['icon'] }}"/></svg>
                        <p class="mt-3 font-semibold">{{ $p['name'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-2xl font-bold text-slate-900">Every portal, in one place</h2>
        <p class="mt-2 max-w-2xl text-slate-500">Sign in once and open whichever system you need — no separate logins, no separate links to remember.</p>
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($portals as $p)
                <div class="rounded-2xl border border-slate-200 p-6">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $p['icon'] }}"/></svg>
                    </span>
                    <h3 class="mt-4 font-semibold text-slate-900">{{ $p['name'] }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $p['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <footer class="bg-brand-950 text-brand-200">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-10 sm:px-6">
            <div>
                <p class="font-semibold text-white">Tanzania Fertilizer Company</p>
                <p class="mt-1 text-sm text-brand-300">TFC Portal — for TFC staff.</p>
            </div>
            <a href="{{ route('login') }}" class="btn bg-white/10 px-5 py-3 text-base text-white ring-1 ring-white/30 hover:bg-white/20">Sign in</a>
        </div>
    </footer>
</body>
</html>
