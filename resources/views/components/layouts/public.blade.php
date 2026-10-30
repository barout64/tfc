@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}TFC Kilimo — {{ __('Subsidised fertilizer for every farmer') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-white font-sans text-slate-800 antialiased" x-data="{ menu: false }">
<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 sm:px-6">
        <a href="{{ route('public.home') }}" class="flex items-center gap-2.5">
            <img src="{{ asset('images/coat-of-arms-sm.png') }}" alt="{{ __('Coat of arms of Tanzania') }}" class="h-11 w-auto shrink-0 drop-shadow">
            <span class="leading-tight"><span class="block font-semibold text-slate-900">TFC Kilimo</span><span class="block text-[11px] text-slate-500">Tanzania Fertilizer Company</span></span>
        </a>
        <nav class="hidden flex-1 items-center gap-1 text-sm md:flex">
            @foreach ([['public.sale-points', __('Find fertilizer')], ['public.prices', __('Prices')], ['public.allowance', __('My allowance')], ['public.advice', __('Farming advice')], ['public.complaint', __('Report a problem')]] as [$route, $label])
                <a href="{{ route($route) }}" @class(['rounded-lg px-3 py-2', 'bg-brand-50 font-medium text-brand-800' => request()->routeIs($route), 'text-slate-600 hover:bg-slate-100' => ! request()->routeIs($route)])>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('locale', app()->getLocale() === 'en' ? 'sw' : 'en') }}" class="btn-ghost btn-sm"><x-icon name="globe" class="size-4" />{{ app()->getLocale() === 'en' ? 'Kiswahili' : 'English' }}</a>
            @auth
                <a href="{{ route('home.redirect') }}" class="btn-primary btn-sm hidden sm:inline-flex">{{ __('Open dashboard') }}</a>
            @else
                <a href="{{ route('login') }}" class="btn-secondary btn-sm hidden sm:inline-flex">{{ __('Staff & dealer login') }}</a>
            @endauth
            <button class="btn-ghost md:hidden" @click="menu = !menu"><x-icon name="menu" /></button>
        </div>
    </div>
    <div x-show="menu" x-cloak class="border-t border-slate-200 px-4 py-2 md:hidden">
        @foreach ([['public.sale-points', __('Find fertilizer')], ['public.prices', __('Prices')], ['public.allowance', __('My allowance')], ['public.register', __('Register as farmer')], ['public.advice', __('Farming advice')], ['public.complaint', __('Report a problem')], ['public.dealer', __('Become an agro-dealer')], ['login', __('Staff & dealer login')]] as [$route, $label])
            <a href="{{ route($route) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">{{ $label }}</a>
        @endforeach
    </div>
</header>

<main class="flex-1">
    @if (session('success') || session('error') || $errors->any())
        <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6"><x-flash /></div>
    @endif
    {{ $slot }}
</main>

<footer class="mt-16 bg-brand-950 text-brand-200">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-4">
        <div class="md:col-span-2">
            <p class="font-semibold text-white">Tanzania Fertilizer Company (TFC)</p>
            <p class="mt-2 max-w-md text-sm text-brand-300">{{ __('Government company under the Ministry of Agriculture, importing and distributing subsidised fertilizer to farmers across Tanzania.') }}</p>
            <p class="mt-4 inline-flex items-center gap-2 rounded-lg bg-brand-900 px-3 py-2 text-sm"><x-icon name="phone" class="size-4 text-harvest-400" /> {{ __('No smartphone? Dial') }} <strong class="text-white">{{ config('tfc.ussd_code') }}</strong></p>
        </div>
        <div class="text-sm">
            <p class="font-semibold text-white">{{ __('Farmers') }}</p>
            <ul class="mt-3 space-y-2">
                <li><a class="hover:text-white" href="{{ route('public.register') }}">{{ __('Register as farmer') }}</a></li>
                <li><a class="hover:text-white" href="{{ route('public.allowance') }}">{{ __('Check my allowance') }}</a></li>
                <li><a class="hover:text-white" href="{{ route('public.sale-points') }}">{{ __('Find a sale point') }}</a></li>
                <li><a class="hover:text-white" href="{{ route('public.complaint') }}">{{ __('Report a problem') }}</a></li>
            </ul>
        </div>
        <div class="text-sm">
            <p class="font-semibold text-white">{{ __('Contact') }}</p>
            <ul class="mt-3 space-y-2">
                <li>Sukari House, Sokoine/Ohio, Dar es Salaam</li>
                <li>{{ config('tfc.hotline') }}</li>
                <li>info@fertilizer.co.tz</li>
                <li><a class="hover:text-white" href="{{ route('public.dealer') }}">{{ __('Become an agro-dealer') }}</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-brand-900 py-4 text-center text-xs text-brand-400">© {{ date('Y') }} Tanzania Fertilizer Company. {{ __('All rights reserved.') }}</div>
</footer>
@stack('scripts')
</body>
</html>
