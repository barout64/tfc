<x-layouts.app :title="__('New dispatch')">
    <x-page-header :title="__('New dispatch')" :subtitle="__('Batches are picked automatically, first-expiry-first-out.')" />

    <form method="POST" action="{{ route('dispatches.store') }}" x-data="dispatchForm()" class="grid gap-6 xl:grid-cols-3">
        @csrf
        <div class="space-y-6 xl:col-span-2">
            <div class="card p-6">
                <h2 class="card-title mb-4">{{ __('Route') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label">{{ __('From warehouse') }} *</label>
                        <select name="from_warehouse_id" x-model="from" @change="estimate()" class="input" required><option value="">{{ __('— Select —') }}</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('To warehouse / sale point') }} *</label>
                        <select name="to_warehouse_id" x-model="to" @change="estimate()" class="input" required><option value="">{{ __('— Select —') }}</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('Road distance (km)') }} *</label><input type="number" name="distance_km" x-model.number="distance" min="1" class="input" required><p class="mt-1 text-xs text-slate-500">{{ __('Pre-filled from GPS (straight line × 1.3). Adjust to actual road distance.') }}</p></div>
                    <x-field :label="__('Planned loading date')" name="planned_date" type="date" :value="now()->toDateString()" required />
                </div>
            </div>

            <div class="card p-6">
                <div class="mb-4 flex items-center justify-between"><h2 class="card-title">{{ __('Load') }}</h2><button type="button" @click="lines.push({ product_id: '', bags: '' })" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" />{{ __('Add product') }}</button></div>
                <template x-for="(line, i) in lines" :key="i">
                    <div class="mb-3 grid grid-cols-12 items-end gap-3">
                        <div class="col-span-5"><label class="label">{{ __('Product') }}</label>
                            <select :name="`lines[${i}][product_id]`" x-model="line.product_id" class="input" required><option value="">{{ __('— Select —') }}</option>@foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->code }} ({{ $p->package_kg }}kg)</option>@endforeach</select>
                        </div>
                        <div class="col-span-3"><label class="label">{{ __('Bags') }}</label><input type="number" min="1" :name="`lines[${i}][bags]`" x-model.number="line.bags" class="input" required></div>
                        <div class="col-span-3 pb-2 text-xs"><span class="text-slate-500">{{ __('Available') }}:</span> <span class="font-medium" :class="available(line.product_id) < line.bags ? 'text-red-600' : 'text-slate-800'" x-text="formatNumber(available(line.product_id)) + ' {{ __('bags') }}'"></span></div>
                        <div class="col-span-1 pb-1"><button type="button" @click="lines.splice(i, 1)" x-show="lines.length > 1" class="btn-ghost btn-sm text-red-600"><x-icon name="x" class="size-4" /></button></div>
                    </div>
                </template>
                <p class="mt-2 text-sm">{{ __('Total load') }}: <strong x-text="tonnes().toFixed(1) + ' t'"></strong></p>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="card-title mb-4">{{ __('Transport') }}</h2>
                <div class="space-y-4">
                    <div><label class="label">{{ __('Transporter') }}</label>
                        <select name="transporter_id" x-model="transporter" class="input"><option value="">{{ __('Assign later') }}</option>@foreach ($transporters as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">{{ __('Vehicle') }}</label>
                        <select name="vehicle_id" x-model="vehicle" class="input"><option value="">{{ __('— Select —') }}</option><template x-for="v in vehicles()" :key="v.id"><option :value="v.id" x-text="`${v.plate_number} (${v.type}, ${Number(v.capacity_tonnes)} t)`"></option></template></select>
                        <p x-show="capacity() && tonnes() > capacity()" class="mt-1 text-xs font-medium text-red-600">{{ __('Load exceeds vehicle capacity!') }}</p>
                    </div>
                    <div><label class="label">{{ __('Driver') }}</label>
                        <select name="driver_id" class="input"><option value="">{{ __('— Select —') }}</option><template x-for="d in drivers()" :key="d.id"><option :value="d.id" x-text="`${d.name} · ${d.phone}`"></option></template></select>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 text-sm"><span class="text-slate-500">{{ __('Estimated transport cost') }}:</span> <strong x-text="'TZS ' + formatNumber(cost())"></strong></div>
                    <x-field :label="__('Notes')" name="notes" type="textarea" />
                </div>
            </div>
            <button class="btn-primary w-full py-2.5">{{ __('Create dispatch') }}</button>
        </div>
    </form>

    @push('scripts')
        <script>
            function dispatchForm() {
                const warehouses = {{ \Illuminate\Support\Js::from($warehouses->mapWithKeys(fn ($w) => [$w->id => [$w->lat, $w->lng]])) }};
                const stock = {{ \Illuminate\Support\Js::from($available) }};
                const transporters = {{ \Illuminate\Support\Js::from($transporters->keyBy('id')) }};
                const products = {{ \Illuminate\Support\Js::from($products->keyBy('id')->map->only('package_kg')) }};
                return {
                    from: '{{ old('from_warehouse_id', request('from')) }}', to: '{{ old('to_warehouse_id') }}', distance: {{ (int) old('distance_km', 0) ?: "''" }},
                    transporter: '{{ old('transporter_id') }}', vehicle: '{{ old('vehicle_id') }}',
                    lines: [{ product_id: '', bags: '' }],
                    available(productId) { return Number(stock[this.from]?.[productId] ?? 0); },
                    tonnes() { return this.lines.reduce((sum, l) => sum + (Number(l.bags) || 0) * (products[l.product_id]?.package_kg ?? 50) / 1000, 0); },
                    vehicles() { return transporters[this.transporter]?.vehicles ?? []; },
                    drivers() { return transporters[this.transporter]?.drivers ?? []; },
                    capacity() { return Number(this.vehicles().find(v => v.id == this.vehicle)?.capacity_tonnes ?? 0); },
                    cost() { return this.tonnes() * (Number(this.distance) || 0) * Number(transporters[this.transporter]?.rate_per_tonne_km ?? 0); },
                    estimate() {
                        const a = warehouses[this.from], b = warehouses[this.to];
                        if (!a || !b || !a[0] || !b[0]) return;
                        const R = 6371, rad = x => x * Math.PI / 180;
                        const dLat = rad(b[0] - a[0]), dLng = rad(b[1] - a[1]);
                        const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a[0])) * Math.cos(rad(b[0])) * Math.sin(dLng / 2) ** 2;
                        this.distance = Math.round(2 * R * Math.asin(Math.sqrt(h)) * 1.3);
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
