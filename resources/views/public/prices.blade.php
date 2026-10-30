<x-layouts.public :title="__('Prices')">
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-semibold text-slate-900">{{ __('Subsidised fertilizer prices') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('The price a farmer pays per bag. The government pays the difference.') }}</p>
        <div class="mt-8 overflow-hidden rounded-2xl border border-slate-200">
            <table class="table">
                <thead><tr><th>{{ __('Fertilizer') }}</th><th>{{ __('Use') }}</th><th class="num">{{ __('Bag') }}</th><th class="num">{{ __('Market price') }}</th><th class="num">{{ __('You pay') }}</th></tr></thead>
                <tbody>
                @foreach ($prices as $row)
                    <tr><td class="font-medium">{{ $row['product']->name }}<p class="text-xs text-slate-500">{{ $row['product']->nutrients }}</p></td><td>{{ __(ucwords(str_replace('_', ' ', $row['product']->category))) }}</td><td class="num">{{ $row['product']->package_kg }} kg</td><td class="num text-slate-500 line-through">{{ number_format($row['price']->market_price) }}</td><td class="num text-lg font-semibold text-brand-700">TZS {{ number_format($row['price']->subsidy_price) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <h2 class="mt-12 text-2xl font-semibold text-slate-900">{{ __('How much fertilizer do I need?') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('Recommended bags per acre. Your subsidised allowance is based on these.') }}</p>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($crops as $crop)
                <div class="rounded-2xl border border-slate-200 p-5">
                    <p class="font-semibold">{{ $crop->name }} <span class="text-sm font-normal text-slate-500">{{ $crop->name_sw }}</span></p>
                    <ul class="mt-2 space-y-1 text-sm">@foreach ($crop->recommendations as $r)<li class="flex justify-between"><span>{{ $r->product->code }} <span class="text-slate-500">({{ __(str_replace('_', ' ', $r->stage)) }})</span></span><strong>{{ $r->bags_per_acre + 0 }} {{ __('bags/acre') }}</strong></li>@endforeach</ul>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.public>
