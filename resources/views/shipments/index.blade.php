<x-layouts.app :title="__('Imports & vessels')">
    <x-page-header :title="__('Imports & vessels')" :subtitle="__('Track every vessel from order to offloading into warehouses.')">
        <x-slot:actions>
            @if (auth()->user()->hasAbility('logistics.manage'))
                <a href="{{ route('shipments.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" />{{ __('Register shipment') }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>
    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Open shipments')" :value="$openCount" icon="ship" tone="blue" />
        <x-stat :label="__('Still to arrive / offload')" :value="number_format($pipelineTonnes, 1).' t'" icon="clock" tone="amber" />
    </div>
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('shipments.index') }}" @class(['btn-sm btn', 'bg-slate-900 text-white' => ! request('status'), 'btn-secondary' => request('status')])>{{ __('All') }}</a>
        @foreach ($statuses as $v => $l)
            <a href="{{ route('shipments.index', ['status' => $v]) }}" @class(['btn-sm btn', 'bg-slate-900 text-white' => request('status') === $v, 'btn-secondary' => request('status') !== $v])>{{ $l }}</a>
        @endforeach
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Vessel') }}</th><th>{{ __('Product') }}</th><th>{{ __('Supplier') }}</th><th>ETA</th><th class="num">{{ __('Ordered (t)') }}</th><th>{{ __('Received') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($shipments as $s)
                @php $pct = $s->ordered_tonnes > 0 ? (int) round($s->receivedTonnes() / $s->ordered_tonnes * 100) : 0; @endphp
                <tr>
                    <td><a href="{{ route('shipments.show', $s) }}" class="link">{{ $s->reference }}</a></td>
                    <td>{{ $s->vessel_name ?: '—' }}<p class="text-xs text-slate-500">{{ $s->port }}</p></td>
                    <td>{{ $s->product->code }}</td>
                    <td>{{ $s->supplier?->name ?: '—' }}</td>
                    <td @class(['text-red-600 font-medium' => $s->isDelayed()])>{{ $s->eta?->format('d M Y') }}</td>
                    <td class="num">{{ number_format($s->ordered_tonnes) }}</td>
                    <td class="min-w-36"><div class="flex items-center gap-2"><x-progress :percent="$pct" color="bg-brand-500" /><span class="w-10 text-right text-xs tabular-nums">{{ $pct }}%</span></div></td>
                    <td><x-badge :color="$s->status->color()">{{ $s->status->label() }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="ship" :title="__('No shipments')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $shipments->links() }}</div>
</x-layouts.app>
