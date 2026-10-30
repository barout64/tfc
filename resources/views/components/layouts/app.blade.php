@props(['title' => null])
@php
    $user = auth()->user();
    $nav = [
        __('Overview') => [
            ['dashboard', __('Dashboard'), 'home', 'dashboard', 'dashboard'],
            ['map', __('Operations map'), 'map', 'map', 'dashboard'],
        ],
        __('Logistics') => [
            ['shipments.index', __('Imports & vessels'), 'ship', 'shipments.*', 'logistics.view'],
            ['dispatches.index', __('Dispatches & tracking'), 'truck', 'dispatches.*', 'logistics.view'],
            ['transporters.index', __('Transporters'), 'star', 'transporters.*', 'logistics.view'],
            ['transporter-payments.index', __('Transport payments'), 'coins', 'transporter-payments.*', 'finance.view'],
        ],
        __('Warehouses & stock') => [
            ['warehouses.index', __('Warehouses'), 'warehouse', 'warehouses.*', 'stock.view'],
            ['stock.index', __('Stock on hand'), 'cube', 'stock.index', 'stock.view'],
            ['stock.ledger', __('Stock ledger'), 'ledger', 'stock.ledger', 'stock.view'],
            ['stock-counts.index', __('Stock counts'), 'clipboard', 'stock-counts.*', 'stock.view'],
            ['stock-reports.index', __('Stock position reports'), 'document', 'stock-reports.*', 'stock.view'],
        ],
        __('Sales & subsidy') => [
            ['sales.create', __('Point of sale'), 'cart', 'sales.create', 'sales.manage'],
            ['sales.index', __('Sales'), 'chart', 'sales.index|sales.show', 'sales.view'],
            ['vouchers.index', __('E-vouchers'), 'ticket', 'vouchers.*', 'farmers.view'],
            ['reservations.index', __('Reservations'), 'clock', 'reservations.*', 'farmers.view'],
        ],
        __('Farmers & partners') => [
            ['farmers.index', __('Farmers'), 'users', 'farmers.*', 'farmers.view'],
            ['cooperatives.index', __('Cooperatives'), 'group', 'cooperatives.*', 'farmers.view'],
            ['dealers.index', __('Agro-dealers'), 'store', 'dealers.*', 'dealers.view'],
        ],
        __('Farmer engagement') => [
            ['complaints.index', __('Complaints'), 'chat', 'complaints.*', 'complaints.manage'],
            ['advisories.index', __('Advisories'), 'megaphone', 'advisories.*', 'advisories.manage'],
            ['sms.index', __('SMS log'), 'phone', 'sms.*', 'complaints.manage'],
        ],
        __('Planning & reports') => [
            ['planning.index', __('Targets & prices'), 'target', 'planning.*', 'planning.manage'],
            ['reports.index', __('Reports'), 'document', 'reports.*', 'reports'],
        ],
        __('Administration') => [
            ['users.index', __('Users & roles'), 'shield', 'users.*', 'users.manage'],
            ['master-data.index', __('Master data'), 'cog', 'master-data.*', 'users.manage'],
            ['audit-logs.index', __('Audit log'), 'clipboard', 'audit-logs.*', 'users.manage'],
        ],
    ];
    $season = \App\Models\Season::current();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}TFC Kilimo</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 font-sans text-slate-800 antialiased" x-data="{ sidebar: false }">
<div class="min-h-screen lg:flex">
    {{-- Sidebar --}}
    <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>
    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'" class="no-print fixed inset-y-0 left-0 z-40 flex w-68 -translate-x-full flex-col bg-brand-950 text-brand-100 transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-5 py-5">
            <img src="{{ asset('images/coat-of-arms-sm.png') }}" alt="{{ __('Coat of arms of Tanzania') }}" class="h-12 w-auto shrink-0 drop-shadow">
            <span>
                <span class="block text-base font-semibold text-white">TFC Kilimo</span>
                <span class="block text-xs text-brand-300">{{ __('Distribution System') }}</span>
            </span>
        </a>
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-6">
            @foreach ($nav as $group => $items)
                @php $visible = collect($items)->filter(fn ($i) => $user->hasAbility($i[4])); @endphp
                @if ($visible->isNotEmpty())
                    <div>
                        <p class="px-3 pb-1.5 text-[11px] font-semibold tracking-wider text-brand-400/80 uppercase">{{ $group }}</p>
                        @foreach ($visible as [$route, $label, $icon, $pattern, $ability])
                            @php $active = collect(explode('|', $pattern))->contains(fn ($p) => request()->routeIs($p)); @endphp
                            <a href="{{ route($route) }}" @class([
                                'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                                'bg-brand-800 font-medium text-white' => $active,
                                'text-brand-200 hover:bg-brand-900 hover:text-white' => ! $active,
                            ])>
                                <x-icon :name="$icon" @class(['size-5', 'text-harvest-400' => $active, 'text-brand-400 group-hover:text-brand-200' => ! $active]) />
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>
        <div class="border-t border-brand-900 px-5 py-4 text-xs text-brand-400">
            {{ __('Need help?') }} <span class="text-brand-200">{{ config('tfc.hotline') }}</span>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Top bar --}}
        <header class="no-print sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="btn-ghost -ml-2 lg:hidden" @click="sidebar = true"><x-icon name="menu" /></button>
            <span class="hidden items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-800 ring-1 ring-brand-200 sm:inline-flex">
                <span class="size-1.5 rounded-full bg-brand-500"></span>{{ __('Season') }} {{ $season->name }}
            </span>
            <div class="flex-1"></div>
            <div class="flex items-center gap-1 text-xs">
                <a href="{{ route('locale', 'en') }}" @class(['rounded px-2 py-1', 'bg-slate-900 text-white' => app()->getLocale() === 'en', 'text-slate-500 hover:bg-slate-100' => app()->getLocale() !== 'en'])>EN</a>
                <a href="{{ route('locale', 'sw') }}" @class(['rounded px-2 py-1', 'bg-slate-900 text-white' => app()->getLocale() === 'sw', 'text-slate-500 hover:bg-slate-100' => app()->getLocale() !== 'sw'])>SW</a>
            </div>
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 rounded-full p-1 pr-2 hover:bg-slate-100">
                    <span class="flex size-8 items-center justify-center rounded-full bg-brand-700 text-xs font-semibold text-white">{{ $user->initials() }}</span>
                    <span class="hidden text-left sm:block">
                        <span class="block text-sm leading-tight font-medium text-slate-800">{{ $user->name }}</span>
                        <span class="block text-xs leading-tight text-slate-500">{{ $user->role->label() }}</span>
                    </span>
                    <x-icon name="chevron-down" class="size-4 text-slate-400" />
                </button>
                <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                    <div class="px-3 py-2 text-xs text-slate-500">{{ $user->email }}@if ($user->warehouse)<br>{{ $user->warehouse->name }}@endif</div>
                    <a href="{{ route('public.home') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"><x-icon name="globe" class="size-4" /> {{ __('Public portal') }}</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50"><x-icon name="logout" class="size-4" /> {{ __('Sign out') }}</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
