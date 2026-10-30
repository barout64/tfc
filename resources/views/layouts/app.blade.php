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
                @if (auth()->user()->hasAbility('dashboard'))
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 hover:bg-brand-800 {{ request()->routeIs(['dashboard', 'warehouses.*', 'stock*', 'shipments.*', 'dispatches.*', 'transporters.*', 'sales.*', 'farmers.*', 'cooperatives.*', 'vouchers.*', 'reservations.*', 'dealers.*', 'complaints.*', 'advisories.*', 'sms.*', 'planning.*', 'reports.*', 'map', 'map.*']) ? 'bg-brand-800' : '' }}">Kilimo</a>
                @endif
                @can('admin')
                    <a href="{{ route('admin.users.index') }}" class="rounded-lg px-3 py-2 hover:bg-brand-800 {{ request()->routeIs('admin.users.*') ? 'bg-brand-800' : '' }}">People &amp; access</a>
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
