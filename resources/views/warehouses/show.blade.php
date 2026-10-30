<x-layouts.app :title="$warehouse->name">
    <x-page-header :title="$warehouse->name" :subtitle="$warehouse->type->label().' · '.$warehouse->region->name.($warehouse->district ? ', '.$warehouse->district->name : '').' · '.$warehouse->code">
        <x-slot:actions>
            @if (auth()->user()->hasAbility('stock.manage'))
                <a href="{{ route('stock-counts.create', ['warehouse' => $warehouse->id]) }}" class="btn-secondary"><x-icon name="clipboard" class="size-4" />{{ __('Stock count') }}</a>
            @endif
            @if (auth()->user()->hasAbility('logistics.manage'))
                <a href="{{ route('dispatches.create', ['from' => $warehouse->id]) }}" class="btn-secondary"><x-icon name="truck" class="size-4" />{{ __('Dispatch from here') }}</a>
            @endif
            @if (auth()->user()->hasAbility('warehouses.manage'))
                <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn-primary"><x-icon name="pencil" class="size-4" />{{ __('Edit') }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Stock')" :value="number_format($warehouse->totalTonnes(), 1).' t'" :sub="number_format($balances->sum('bags')).' '.__('bags')" icon="cube" />
        <x-stat :label="__('Capacity')" :value="number_format($warehouse->capacity_tonnes).' t'" :sub="$warehouse->utilisationPercent().'% '.__('used')" icon="warehouse" tone="blue" />
        <x-stat :label="__('Batches')" :value="$balances->count()" :sub="$balances->filter(fn ($b) => $b->batch->isExpiringSoon(config('tfc.expiry_warning_days')))->count().' '.__('expiring soon')" icon="ledger" tone="amber" />
        <x-stat :label="__('Manager')" :value="$warehouse->manager?->name ?? '—'" :sub="$warehouse->contact_phone" icon="users" tone="violet" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">{{ __('Stock by batch') }}</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Batch') }}</th><th>{{ __('Expires') }}</th><th class="num">{{ __('Bags') }}</th><th class="num">{{ __('Tonnes') }}</th></tr></thead>
                    <tbody>
                    @forelse ($balances as $b)
                        <tr>
                            <td><span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full" style="background: {{ $b->batch->product->color }}"></span>{{ $b->batch->product->code }}</span></td>
                            <td class="font-mono text-xs">{{ $b->batch->batch_number }}</td>
                            <td>@if ($b->batch->expires_on)<span @class(['text-red-600 font-medium' => $b->batch->isExpiringSoon(config('tfc.expiry_warning_days'))])>{{ $b->batch->expires_on->format('d M Y') }}</span>@else — @endif</td>
                            <td class="num">{{ number_format($b->bags) }}</td>
                            <td class="num font-medium">{{ number_format($b->batch->product->bagsToTonnes($b->bags), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty icon="cube" :title="__('No stock at this location')" /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">{{ __('Location') }}</h2></div>
            @if ($warehouse->lat)
                <div id="whMap" class="h-64"></div>
            @else
                <x-empty icon="location" :title="__('No GPS coordinates')" :text="__('Edit the warehouse to add latitude and longitude.')" />
            @endif
            <div class="space-y-1 p-4 text-sm text-slate-600">
                <p>{{ $warehouse->address ?: '—' }}</p>
                <p>{{ __('TFRA no.') }}: {{ $warehouse->tfra_number ?: '—' }}</p>
            </div>
        </div>
    </div>

    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">{{ __('Recent movements') }}</h2><a href="{{ route('stock.ledger', ['warehouse' => $warehouse->id]) }}" class="link text-xs">{{ __('Full ledger') }}</a></div>
        <div class="overflow-x-auto">
            @include('stock._movements', ['movements' => $movements, 'showWarehouse' => false])
        </div>
    </div>

    @if ($warehouse->lat)
        @push('scripts')
            <script type="module">
                const map = tfcMap('whMap', { zoom: 10 });
                map.setView([{{ $warehouse->lat }}, {{ $warehouse->lng }}], 11);
                L.marker([{{ $warehouse->lat }}, {{ $warehouse->lng }}], { icon: tfcDot('{{ $warehouse->type->color() }}', 18) }).addTo(map);
            </script>
        @endpush
    @endif
</x-layouts.app>
