<x-layouts.app :title="__('Point of sale')">
    <x-page-header :title="__('Point of sale')" :subtitle="__('Sell subsidised fertilizer to farmers, agro-dealers or cooperatives.')" />

    <form method="POST" action="{{ route('sales.store') }}" x-data="pos()" class="grid gap-6 xl:grid-cols-3">
        @csrf
        <div class="space-y-6 xl:col-span-2">
            <div class="card p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field :label="__('Selling from')" name="warehouse_id" :options="$warehouses->pluck('name', 'id')" :value="auth()->user()->warehouse_id ?? $warehouses->first()?->id" required />
                    <div>
                        <label class="label">{{ __('Customer type') }}</label>
                        <div class="grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1 text-sm">
                            @foreach (['farmer' => __('Farmer'), 'agro_dealer' => __('Agro-dealer'), 'cooperative' => __('Cooperative')] as $v => $l)
                                <label class="cursor-pointer"><input type="radio" name="customer_type" value="{{ $v }}" x-model="type" class="peer sr-only"><span class="block rounded-md py-1.5 text-center peer-checked:bg-white peer-checked:font-medium peer-checked:shadow-sm">{{ $l }}</span></label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Farmer lookup --}}
                <div x-show="type === 'farmer'" class="mt-5">
                    <label class="label">{{ __('Find farmer — phone, farmer no., NIDA, voucher or reservation code') }}</label>
                    <div class="flex gap-2">
                        <input x-model="query" @keydown.enter.prevent="lookup()" class="input" placeholder="0712345678 / TFC-F-2026-000001 / 8K3QZ7MA">
                        <button type="button" @click="lookup()" class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Find') }}</button>
                    </div>
                    <p x-show="notFound" x-cloak class="mt-2 text-sm text-red-600">{{ __('No farmer found.') }} <a href="{{ route('farmers.create') }}" class="link">{{ __('Register new farmer') }}</a></p>

                    <template x-if="farmer">
                        <div class="mt-4 rounded-xl border border-brand-200 bg-brand-50/50 p-4">
                            <input type="hidden" name="farmer_id" :value="farmer.id">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-slate-900" x-text="farmer.name"></p>
                                    <p class="text-xs text-slate-600" x-text="farmer.number + ' · ' + farmer.phone + ' · ' + farmer.acres + ' {{ __('acres') }}'"></p>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="farmer.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700'" x-text="farmer.status"></span>
                            </div>
                            <p class="mt-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ __('Remaining subsidised allowance') }}</p>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <template x-for="a in allowance" :key="a.product_id">
                                    <span class="rounded-lg bg-white px-3 py-1.5 text-sm ring-1 ring-slate-200"><strong x-text="a.code"></strong> <span x-text="a.remaining + ' / ' + a.allowed"></span></span>
                                </template>
                                <span x-show="!allowance.length" class="text-sm text-red-600">{{ __('No allowance this season') }}</span>
                            </div>
                            <template x-if="voucher">
                                <p class="mt-3 text-sm"><x-icon name="ticket" class="inline size-4" /> {{ __('Voucher') }} <strong x-text="voucher.code"></strong>: <span x-text="voucher.bags + ' bags · ' + voucher.status"></span><input type="hidden" name="voucher_code" :value="voucher.usable ? voucher.code : ''"></p>
                            </template>
                            <template x-if="reservation">
                                <p class="mt-3 text-sm"><x-icon name="clock" class="inline size-4" /> {{ __('Reservation') }} <strong x-text="reservation.code"></strong>: <span x-text="reservation.bags + ' bags'"></span><input type="hidden" name="reservation_code" :value="reservation.code"></p>
                            </template>
                        </div>
                    </template>
                </div>

                <div x-show="type === 'agro_dealer'" x-cloak class="mt-5">
                    <x-field :label="__('Agro-dealer')" name="agro_dealer_id" :options="$dealers->mapWithKeys(fn ($d) => [$d->id => $d->business_name.' ('.$d->dealer_number.')'])" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('Bags sold to a dealer are moved into the dealer\'s outlet stock automatically.') }}</p>
                </div>
                <div x-show="type === 'cooperative'" x-cloak class="mt-5">
                    <x-field :label="__('Cooperative')" name="cooperative_id" :options="$cooperatives->pluck('name', 'id')" />
                </div>
            </div>

            <div class="card p-6">
                <h2 class="card-title mb-4">{{ __('Products') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <template x-for="(p, i) in products" :key="p.id">
                        <div class="flex items-center gap-3 rounded-lg border border-slate-200 p-3" :class="lines[i] > 0 && 'border-brand-400 bg-brand-50/40'">
                            <div class="flex-1">
                                <p class="font-medium" x-text="p.code"></p>
                                <p class="text-xs text-slate-500" x-text="'TZS ' + formatNumber(p.price) + ' / {{ __('bag') }}'"></p>
                                <p x-show="type === 'farmer' && farmer" class="text-xs" :class="lines[i] > remaining(p.id) ? 'text-red-600 font-medium' : 'text-slate-500'" x-text="'{{ __('Allowed') }}: ' + remaining(p.id)"></p>
                            </div>
                            <input type="hidden" :name="`lines[${i}][product_id]`" :value="p.id">
                            <input type="number" min="0" :name="`lines[${i}][bags]`" x-model.number="lines[i]" class="input w-24 text-right" placeholder="0">
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div>
            <div class="card sticky top-20 p-6">
                <h2 class="card-title">{{ __('Summary') }}</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Bags') }}</dt><dd class="font-medium tabular-nums" x-text="formatNumber(totalBags())"></dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-lg"><dt class="font-semibold">{{ __('Total') }}</dt><dd class="font-semibold tabular-nums" x-text="'TZS ' + formatNumber(total())"></dd></div>
                </dl>
                <div class="mt-5 space-y-3">
                    <div><label class="label">{{ __('Payment method') }}</label>
                        <select name="payment_method" x-model="method" class="input">
                            @foreach ($methods as $v => $l)<option value="{{ $v }}" x-show="'{{ $v }}' !== 'credit' || type !== 'farmer'">{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div x-show="!['cash', 'bank', 'credit'].includes(method)"><x-field :label="__('Payer phone (for mobile money / control number)')" name="payer_phone" placeholder="07XXXXXXXX" /></div>
                </div>
                <button class="btn-primary mt-6 w-full py-3 text-base" :disabled="totalBags() === 0 || (type === 'farmer' && !farmer)">{{ __('Complete sale') }}</button>
                <p class="mt-3 text-center text-xs text-slate-500">{{ __('The farmer receives an SMS receipt.') }}</p>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            function pos() {
                const products = {{ \Illuminate\Support\Js::from($products) }};
                return {
                    type: '{{ old('customer_type', 'farmer') }}', method: 'cash', query: '', notFound: false,
                    farmer: null, allowance: [], voucher: null, reservation: null,
                    products, lines: products.map(() => ''),
                    async lookup() {
                        this.notFound = false;
                        const res = await fetch(`{{ route('sales.lookup') }}?q=${encodeURIComponent(this.query)}`, { headers: { Accept: 'application/json' } });
                        const data = await res.json();
                        if (!data.found) { this.farmer = null; this.notFound = true; return; }
                        Object.assign(this, { farmer: data.farmer, allowance: data.allowance, voucher: data.voucher, reservation: data.reservation });
                        const held = data.voucher?.usable ? data.voucher : data.reservation;
                        if (held) this.lines = products.map(p => p.id === held.product_id ? held.bags : '');
                    },
                    remaining(productId) {
                        const base = this.allowance.find(a => a.product_id === productId)?.remaining ?? 0;
                        const held = [this.voucher?.usable ? this.voucher : null, this.reservation].filter(h => h && h.product_id === productId).reduce((s, h) => s + h.bags, 0);
                        return base + held;
                    },
                    totalBags() { return this.lines.reduce((s, b) => s + (Number(b) || 0), 0); },
                    total() { return this.products.reduce((s, p, i) => s + (Number(this.lines[i]) || 0) * p.price, 0); },
                };
            }
        </script>
    @endpush
</x-layouts.app>
