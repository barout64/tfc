<x-layouts.public :title="__('Find fertilizer')">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-semibold text-slate-900">{{ __('Find fertilizer near you') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('Live stock at TFC sale points and approved agro-dealers.') }}</p>
        <form class="mt-6 grid gap-3 rounded-2xl border border-slate-200 p-4 sm:grid-cols-4">
            <select name="region" class="input"><option value="">{{ __('All regions') }}</option>@foreach ($regions as $r)<option value="{{ $r->id }}" @selected($regionId == $r->id)>{{ $r->name }}</option>@endforeach</select>
            <select name="product" class="input"><option value="">{{ __('Any fertilizer') }}</option>@foreach ($products as $p)<option value="{{ $p->id }}" @selected($productId == $p->id)>{{ $p->name }}</option>@endforeach</select>
            <button class="btn-primary"><x-icon name="search" class="size-4" />{{ __('Search') }}</button>
            <button type="button" class="btn-secondary" onclick="navigator.geolocation.getCurrentPosition(p => window.salePointsMap?.setView([p.coords.latitude, p.coords.longitude], 10))"><x-icon name="location" class="size-4" />{{ __('Near me') }}</button>
        </form>
        <div class="mt-6 grid gap-6 lg:grid-cols-5">
            <div class="overflow-hidden rounded-2xl border border-slate-200 lg:col-span-3"><div id="spMap" class="h-[60vh]"></div></div>
            <div class="max-h-[60vh] space-y-3 overflow-y-auto lg:col-span-2">
                @forelse ($points as $p)
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div><p class="font-semibold text-slate-900">{{ $p->name }}</p><p class="text-xs text-slate-500">{{ $p->type->label() }} · {{ $p->region->name }}{{ $p->district ? ', '.$p->district->name : '' }}</p></div>
                            @if ($p->stock->isNotEmpty())<x-badge color="green">{{ __('In stock') }}</x-badge>@else<x-badge color="red">{{ __('Out of stock') }}</x-badge>@endif
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($p->stock as $s)<span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-xs ring-1 ring-slate-200"><span class="size-2 rounded-full" style="background: {{ $s->color }}"></span><strong>{{ $s->code }}</strong> {{ number_format($s->bags) }} {{ __('bags') }}</span>@endforeach
                        </div>
                        @if ($p->contact_phone || $p->address)<p class="mt-2 text-xs text-slate-500">{{ $p->address }} @if ($p->contact_phone)· <a href="tel:{{ $p->contact_phone }}" class="link">{{ $p->contact_phone }}</a>@endif</p>@endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200"><x-empty icon="store" :title="__('No sale points found')" :text="__('Try another region or call :phone', ['phone' => config('tfc.hotline')])" /></div>
                @endforelse
            </div>
        </div>
    </div>
    @push('scripts')
        <script type="module">
            const map = window.salePointsMap = tfcMap('spMap', { scrollWheelZoom: true });
            const points = {{ \Illuminate\Support\Js::from($points->filter(fn ($p) => $p->lat)->map(fn ($p) => ['name' => $p->name, 'lat' => $p->lat, 'lng' => $p->lng, 'stock' => $p->stock->map(fn ($s) => $s->code.': '.number_format($s->bags))->implode('<br>'), 'has' => $p->stock->isNotEmpty()])->values()) }};
            const bounds = [];
            points.forEach(p => {
                L.marker([p.lat, p.lng], { icon: tfcDot(p.has ? '#16a34a' : '#dc2626', 16) }).bindPopup(`<b>${p.name}</b><br>${p.stock || '{{ __('Out of stock') }}'}`).addTo(map);
                bounds.push([p.lat, p.lng]);
            });
            if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30] });
        </script>
    @endpush
</x-layouts.public>
