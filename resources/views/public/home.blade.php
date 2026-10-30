<x-layouts.public>
    <section class="relative overflow-hidden bg-brand-950 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(72,181,119,.35),transparent_50%),radial-gradient(circle_at_85%_70%,rgba(245,184,61,.25),transparent_45%)]"></div>
        <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-24">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs ring-1 ring-white/20"><span class="size-1.5 rounded-full bg-harvest-400"></span>{{ __('Season :season', ['season' => $season->name]) }}</span>
                <h1 class="mt-5 text-4xl leading-tight font-semibold sm:text-5xl">{{ __('Subsidised fertilizer,') }}<br><span class="text-harvest-400">{{ __('closer to every farmer.') }}</span></h1>
                <p class="mt-5 max-w-lg text-lg text-brand-200">{{ __('Find the nearest sale point with stock, check your allowance, reserve bags and report problems — online or by USSD.') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('public.sale-points') }}" class="btn bg-harvest-400 px-5 py-3 text-base text-brand-950 hover:bg-harvest-500"><x-icon name="location" class="size-5" />{{ __('Find fertilizer near me') }}</a>
                    <a href="{{ route('public.register') }}" class="btn bg-white/10 px-5 py-3 text-base text-white ring-1 ring-white/30 hover:bg-white/20">{{ __('Register as farmer') }}</a>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 self-center">
                @foreach ([[number_format($farmers), __('registered farmers'), 'users'], [number_format($sales['tonnes']).' t', __('distributed this season'), 'truck'], [number_format($salePoints), __('sale points & agro-dealers'), 'store'], [config('tfc.ussd_code'), __('USSD for any phone'), 'phone']] as [$value, $label, $icon])
                    <div class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/10 backdrop-blur">
                        <x-icon :name="$icon" class="size-6 text-harvest-400" />
                        <p class="mt-3 text-2xl font-semibold">{{ $value }}</p>
                        <p class="text-sm text-brand-300">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <h2 class="text-2xl font-semibold text-slate-900">{{ __('How it works') }}</h2>
        <div class="mt-8 grid gap-6 md:grid-cols-4">
            @foreach ([['users', __('1. Register'), __('Online, at a TFC office, or dial :ussd. You get a farmer number by SMS.', ['ussd' => config('tfc.ussd_code')])], ['ticket', __('2. Get your allowance'), __('Your subsidised bags are calculated from your farm size and crop.')], ['location', __('3. Find & reserve'), __('See which sale points have stock today and reserve your bags.')], ['cart', __('4. Buy at subsidised price'), __('Pay cash or mobile money. Receive an SMS receipt.')]] as [$icon, $title, $text])
                <div class="rounded-2xl border border-slate-200 p-6">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700"><x-icon :name="$icon" class="size-6" /></span>
                    <h3 class="mt-4 font-semibold text-slate-900">{{ $title }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bg-slate-50">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2">
            <div>
                <div class="flex items-end justify-between"><h2 class="text-2xl font-semibold text-slate-900">{{ __('Subsidised prices') }}</h2><a href="{{ route('public.prices') }}" class="link text-sm">{{ __('All prices') }}</a></div>
                <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    @foreach ($prices as $row)
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 last:border-0">
                            <div class="flex items-center gap-3"><span class="size-3 rounded-full" style="background: {{ $row['product']->color }}"></span><div><p class="font-medium">{{ $row['product']->name }}</p><p class="text-xs text-slate-500">{{ $row['product']->package_kg }} kg · {{ __('market') }} TZS {{ number_format($row['price']->market_price) }}</p></div></div>
                            <p class="text-lg font-semibold text-brand-700">TZS {{ number_format($row['price']->subsidy_price) }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ __('If anyone charges more than this, report it — it is free and confidential.') }} <a href="{{ route('public.complaint') }}" class="link">{{ __('Report overpricing') }}</a></p>
            </div>
            <div>
                <div class="flex items-end justify-between"><h2 class="text-2xl font-semibold text-slate-900">{{ __('Farming advice') }}</h2><a href="{{ route('public.advice') }}" class="link text-sm">{{ __('More advice') }}</a></div>
                <div class="mt-6 space-y-4">
                    @forelse ($advisories as $a)
                        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="font-semibold">{{ $a->title }}</p><p class="mt-1 text-sm text-slate-600">{{ str($a->body)->limit(160) }}</p></div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('No advice published yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="grid items-center gap-8 rounded-3xl bg-gradient-to-br from-brand-700 to-brand-900 p-8 text-white sm:p-12 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-semibold">{{ __('Become a TFC agro-dealer') }}</h2>
                <p class="mt-3 text-brand-100">{{ __('Licensed by TFRA? Apply online, buy from TFC warehouses and serve farmers in your area with your own dealer dashboard.') }}</p>
            </div>
            <div class="lg:text-right"><a href="{{ route('public.dealer') }}" class="btn bg-white px-5 py-3 text-base text-brand-800 hover:bg-brand-50">{{ __('Apply now') }} <x-icon name="arrow-right" class="size-5" /></a></div>
        </div>
    </section>
</x-layouts.public>
