<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · TFC Portal</title>
    <link rel="icon" href="/images/coat-of-arms-sm.png" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-brand-950 text-slate-800 antialiased">
<div class="grid min-h-full lg:grid-cols-2">
    <section class="relative hidden overflow-hidden bg-gradient-to-br from-brand-900 via-brand-800 to-brand-950 p-12 text-white lg:flex lg:flex-col">
        <div class="absolute -right-24 -bottom-24 size-96 rounded-full bg-harvest-400/10"></div>
        <div class="absolute -top-16 -left-16 size-72 rounded-full bg-brand-400/10"></div>
        <div class="relative flex items-center gap-3">
            <img src="/images/coat-of-arms-sm.png" alt="" class="h-14 w-auto">
            <div>
                <p class="text-xs font-semibold tracking-[0.2em] text-harvest-300 uppercase">United Republic of Tanzania</p>
                <p class="text-lg font-bold">Tanzania Fertilizer Company</p>
            </div>
        </div>
        <div class="relative my-auto max-w-md">
            <h1 class="text-4xl leading-tight font-extrabold">One sign-in for every TFC system.</h1>
            <p class="mt-4 text-brand-100">Sign in once, then open the system you need: meetings, official correspondence or fertilizer distribution.</p>
            <ul class="mt-8 space-y-3 text-sm text-brand-50">
                <li class="flex items-center gap-3"><span class="size-2 rounded-full bg-harvest-400"></span>TFC Meetings</li>
                <li class="flex items-center gap-3"><span class="size-2 rounded-full bg-harvest-400"></span>TFC Protocol</li>
                <li class="flex items-center gap-3"><span class="size-2 rounded-full bg-harvest-400"></span>TFC Kilimo</li>
            </ul>
        </div>
    </section>

    <section class="flex items-center justify-center bg-slate-50 px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center lg:hidden">
                <img src="/images/coat-of-arms-sm.png" alt="" class="mx-auto h-16 w-auto">
                <p class="mt-3 font-bold text-brand-900">Tanzania Fertilizer Company</p>
            </div>
            <h2 class="text-2xl font-bold text-slate-900">Sign in to TFC Portal</h2>
            <p class="mt-1 text-sm text-slate-500">Use the email and password given by the TFC administrator.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="label">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="field">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600"> Keep me signed in
                </label>
                <button class="btn-primary w-full py-2.5">Sign in</button>
            </form>
        </div>
    </section>
</div>
</body>
</html>
