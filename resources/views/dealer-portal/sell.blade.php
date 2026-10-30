<x-layouts.portal :title="__('Sell to farmer')" :subtitle="auth()->user()->agroDealer->business_name">
    <h1 class="mb-1 text-2xl font-semibold">{{ __('Sell to farmer') }}</h1>
    <p class="mb-6 text-sm text-slate-500">{{ __('The farmer\'s subsidised allowance is checked automatically. They get an SMS receipt.') }}</p>
    <form method="POST" action="{{ route('dealer.sell') }}" class="card max-w-2xl space-y-5 p-6" x-data="{ products: @js($products), lines: @js($products->map(fn () => '')) }">
        @csrf
        <x-field :label="__('Farmer phone or farmer number')" name="farmer" required placeholder="0712345678" />
        <x-field :label="__('Voucher code (if any)')" name="voucher_code" placeholder="8K3QZ7MA" />
        <div>
            <p class="label">{{ __('Bags') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <template x-for="(p, i) in products" :key="p.id">
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                        <span class="flex-1"><span class="block font-medium" x-text="p.code"></span><span class="text-xs text-slate-500" x-text="'TZS ' + formatNumber(p.price)"></span></span>
                        <input type="hidden" :name="`lines[${i}][product_id]`" :value="p.id">
                        <input type="number" min="0" :name="`lines[${i}][bags]`" x-model.number="lines[i]" class="input w-24 text-right" placeholder="0">
                    </label>
                </template>
            </div>
        </div>
        <x-field :label="__('Payment method')" name="payment_method" :options="$methods" value="cash" required />
        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
            <p class="text-lg font-semibold">TZS <span x-text="formatNumber(products.reduce((s, p, i) => s + (Number(lines[i]) || 0) * p.price, 0))"></span></p>
            <div class="flex gap-2"><a href="{{ route('dealer.home') }}" class="btn-secondary">{{ __('Cancel') }}</a><button class="btn-primary">{{ __('Record sale') }}</button></div>
        </div>
    </form>
</x-layouts.portal>
