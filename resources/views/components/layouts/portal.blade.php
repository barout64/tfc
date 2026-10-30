@props(['title' => null, 'subtitle' => null])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}TFC Kilimo</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
<header class="bg-brand-950 text-white">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4">
        <img src="{{ asset('images/coat-of-arms-sm.png') }}" alt="{{ __('Coat of arms of Tanzania') }}" class="h-11 w-auto shrink-0 drop-shadow">
        <div class="leading-tight"><p class="font-semibold">TFC Kilimo</p><p class="text-xs text-brand-300">{{ $subtitle }}</p></div>
        <div class="ml-auto flex items-center gap-2 text-sm">
            <a href="{{ route('locale', app()->getLocale() === 'en' ? 'sw' : 'en') }}" class="rounded px-2 py-1 text-brand-200 hover:bg-brand-900">{{ app()->getLocale() === 'en' ? 'SW' : 'EN' }}</a>
            <span class="hidden text-brand-200 sm:inline">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg bg-brand-900 px-3 py-1.5 hover:bg-brand-800">{{ __('Sign out') }}</button></form>
        </div>
    </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-6"><x-flash />{{ $slot }}</main>
@stack('scripts')
</body>
</html>
