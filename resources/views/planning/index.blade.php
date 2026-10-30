<x-layouts.app :title="__('Targets & prices')">
    <x-page-header :title="__('Season planning · :season', ['season' => $season->name])" :subtitle="__('Distribution targets per region and subsidised prices per product.')" />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">{{ __('Regional targets vs sold') }}</h2><span class="text-sm text-slate-500">{{ __('Total') }}: {{ number_format($progress->sum('target')) }} t</span></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>{{ __('Region') }}</th>@foreach ($products as $p)<th class="num">{{ $p->code }}</th>@endforeach<th class="num">{{ __('Total') }}</th><th class="num">{{ __('Sold') }}</th><th class="w-40">{{ __('Progress') }}</th></tr></thead>
                    <tbody>
                    @forelse ($progress as $row)
                        @php $byProduct = ($targets[$row->id] ?? collect())->keyBy('product_id'); @endphp
                        <tr>
                            <td class="font-medium">{{ $row->name }}</td>
                            @foreach ($products as $p)<td class="num text-xs">{{ isset($byProduct[$p->id]) ? number_format($byProduct[$p->id]->target_tonnes) : '—' }}</td>@endforeach
                            <td class="num font-medium">{{ number_format($row->target) }}</td>
                            <td class="num">{{ number_format($row->sold, 1) }}</td>
                            <td><div class="flex items-center gap-2"><x-progress :percent="$row->percent" /><span class="w-9 text-right text-xs tabular-nums">{{ $row->percent }}%</span></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $products->count() + 4 }}"><x-empty icon="target" :title="__('No targets yet')" /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="space-y-6">
            <form method="POST" action="{{ route('planning.targets.store') }}" class="card space-y-3 p-5">
                @csrf
                <h3 class="card-title">{{ __('Set / update target') }}</h3>
                <x-field :label="__('Region')" name="region_id" :options="$regions->pluck('name', 'id')" required />
                <x-field :label="__('Product')" name="product_id" :options="$products->pluck('code', 'id')" required />
                <x-field :label="__('Target (tonnes)')" name="target_tonnes" type="number" step="0.01" required />
                <button class="btn-primary w-full">{{ __('Save target') }}</button>
            </form>
            <div class="card p-5">
                <h3 class="card-title mb-3">{{ __('Stock available vs national target') }}</h3>
                @foreach ($products as $p)
                    @php $target = $targets->flatten()->where('product_id', $p->id)->sum('target_tonnes'); $have = (float) ($stock[$p->id]->tonnes ?? 0); @endphp
                    <div class="mb-3">
                        <div class="mb-1 flex justify-between text-xs"><span class="font-medium">{{ $p->code }}</span><span class="text-slate-500 tabular-nums">{{ number_format($have, 0) }} / {{ number_format($target) }} t</span></div>
                        <x-progress :percent="$target ? $have / $target * 100 : 0" color="bg-sky-500" />
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">{{ __('Price list') }}</h2></div>
            <table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Region') }}</th><th class="num">{{ __('Market price') }}</th><th class="num">{{ __('Farmer pays') }}</th><th class="num">{{ __('Subsidy') }}</th><th>{{ __('From') }}</th></tr></thead>
                <tbody>
                @forelse ($prices as $price)
                    <tr><td>{{ $price->product->code }}</td><td>{{ $price->region?->name ?? __('National') }}</td><td class="num">{{ number_format($price->market_price) }}</td><td class="num font-semibold">{{ number_format($price->subsidy_price) }}</td><td class="num text-emerald-700">{{ number_format($price->subsidyPerBag()) }}</td><td class="text-xs">{{ $price->effective_from->format('d M Y') }}</td></tr>
                @empty
                    <tr><td colspan="6"><x-empty icon="coins" :title="__('No prices set')" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('planning.prices.store') }}" class="card space-y-3 p-5">
            @csrf
            <h3 class="card-title">{{ __('Set price (per bag)') }}</h3>
            <x-field :label="__('Product')" name="product_id" :options="$products->pluck('code', 'id')" required />
            <x-field :label="__('Region (blank = national)')" name="region_id" :options="$regions->pluck('name', 'id')" :placeholder="__('National')" />
            <x-field :label="__('Market price (TZS)')" name="market_price" type="number" required />
            <x-field :label="__('Subsidised price farmer pays (TZS)')" name="subsidy_price" type="number" required />
            <x-field :label="__('Effective from')" name="effective_from" type="date" :value="now()->toDateString()" required />
            <button class="btn-primary w-full">{{ __('Save price') }}</button>
        </form>
    </div>
</x-layouts.app>
