<x-layouts.app :title="__('Operations map')">
    <x-page-header :title="__('Live operations map')" :subtitle="__('Warehouses with current stock and trucks on the road. Refreshes every 30 seconds.')" />

    <div class="grid gap-4 xl:grid-cols-4" x-data="opsMap()">
        <div class="card self-start overflow-hidden xl:col-span-3">
            <div x-ref="map" class="h-[70vh] w-full"></div>
        </div>
        <div class="space-y-4">
            <div class="card p-4">
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ __('Legend') }}</p>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach (\App\Enums\WarehouseType::cases() as $type)
                        @continue($type === \App\Enums\WarehouseType::AgroDealer)
                        <li class="flex items-center gap-2"><span class="size-3 rounded-full ring-2 ring-white" style="background: {{ $type->color() }}"></span>{{ $type->label() }}</li>
                    @endforeach
                    <li class="flex items-center gap-2"><span class="size-3 rounded-full bg-sky-600"></span>{{ __('Truck on schedule') }}</li>
                    <li class="flex items-center gap-2"><span class="size-3 rounded-full bg-red-600"></span>{{ __('Truck overdue / no signal') }}</li>
                </ul>
                <p class="mt-3 text-xs text-slate-500">{{ __('Circle size = tonnes in stock.') }}</p>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Trucks') }} (<span x-text="trucks.length"></span>)</h2></div>
                <div class="max-h-[50vh] divide-y divide-slate-100 overflow-y-auto">
                    <template x-for="t in trucks" :key="t.number">
                        <button type="button" @click="focus(t)" class="block w-full px-4 py-3 text-left hover:bg-slate-50">
                            <p class="text-sm font-medium text-slate-800"><span x-text="t.plate"></span> <span class="text-xs text-slate-500" x-text="'· ' + t.tonnes + ' t'"></span></p>
                            <p class="truncate text-xs text-slate-500" x-text="t.from[2] + ' → ' + t.to[2]"></p>
                            <p class="mt-0.5 text-xs" :class="t.late || t.silent ? 'text-red-600' : 'text-slate-500'" x-text="(t.late ? '{{ __('Overdue') }} · ' : '') + '{{ __('Seen') }} ' + t.last_seen"></p>
                        </button>
                    </template>
                    <p x-show="!trucks.length" class="px-4 py-6 text-center text-sm text-slate-500">{{ __('No trucks in transit.') }}</p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function opsMap() {
                return {
                    map: null, layer: null, trucks: [],
                    colors: {{ \Illuminate\Support\Js::from(collect(\App\Enums\WarehouseType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->color()])) }},
                    init() {
                        this.map = tfcMap(this.$refs.map, { scrollWheelZoom: true });
                        this.layer = L.layerGroup().addTo(this.map);
                        this.load();
                        setInterval(() => this.load(), 30000);
                    },
                    async load() {
                        const res = await fetch('{{ route('map.data') }}', { headers: { Accept: 'application/json' } });
                        const data = await res.json();
                        this.trucks = data.trucks;
                        this.layer.clearLayers();
                        data.warehouses.forEach(w => {
                            const radius = Math.max(6, Math.min(28, Math.sqrt(w.tonnes) / 2 + 6));
                            L.circleMarker([w.lat, w.lng], { radius, color: '#fff', weight: 2, fillColor: this.colors[w.type] || '#16a34a', fillOpacity: .85 })
                                .bindPopup(`<b>${w.name}</b><br>${formatNumber(w.tonnes, 1)} t ${w.capacity ? '/ ' + formatNumber(w.capacity) + ' t' : ''}<br><a href="${w.url}">{{ __('View') }} →</a>`)
                                .addTo(this.layer);
                        });
                        data.trucks.forEach(t => {
                            L.polyline([[t.from[0], t.from[1]], [t.lat, t.lng], [t.to[0], t.to[1]]], { color: t.late ? '#dc2626' : '#0284c7', weight: 2, dashArray: '6 6', opacity: .7 }).addTo(this.layer);
                            t.marker = L.marker([t.lat, t.lng], { icon: tfcTruck(t.late || t.silent ? '#dc2626' : '#0284c7') })
                                .bindPopup(`<b>${t.plate}</b> · ${t.number}<br>${t.from[2]} → ${t.to[2]}<br>${t.tonnes} t · {{ __('Driver') }}: ${t.driver ?? '-'}<br>ETA ${t.eta}<br><a href="${t.url}">{{ __('View') }} →</a>`)
                                .addTo(this.layer);
                        });
                    },
                    focus(t) {
                        this.map.setView([t.lat, t.lng], 9);
                        t.marker?.openPopup();
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
