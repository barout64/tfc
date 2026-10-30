<table class="table">
    <thead><tr><th>{{ __('Date') }}</th>@if ($showWarehouse ?? true)<th>{{ __('Warehouse') }}</th>@endif<th>{{ __('Type') }}</th><th>{{ __('Product / batch') }}</th><th class="num">{{ __('Bags') }}</th><th class="num">{{ __('Balance') }}</th><th>{{ __('Reference') }}</th><th>{{ __('By') }}</th></tr></thead>
    <tbody>
    @forelse ($movements as $m)
        <tr>
            <td class="text-xs whitespace-nowrap">{{ $m->created_at->format('d M Y H:i') }}</td>
            @if ($showWarehouse ?? true)<td>{{ $m->warehouse->name }}</td>@endif
            <td><x-badge :color="$m->bags > 0 ? 'green' : 'amber'">{{ $m->type->label() }}</x-badge></td>
            <td><span class="font-medium">{{ $m->batch->product->code }}</span> <span class="font-mono text-xs text-slate-500">{{ $m->batch->batch_number }}</span></td>
            <td @class(['num font-medium', 'text-emerald-700' => $m->bags > 0, 'text-red-600' => $m->bags < 0])>{{ $m->bags > 0 ? '+' : '' }}{{ number_format($m->bags) }}</td>
            <td class="num">{{ number_format($m->balance_after) }}</td>
            <td class="text-xs">
                @php $ref = $m->reference; @endphp
                @if ($ref instanceof \App\Models\Dispatch)<a class="link" href="{{ route('dispatches.show', $ref) }}">{{ $ref->number }}</a>
                @elseif ($ref instanceof \App\Models\Sale)<a class="link" href="{{ route('sales.show', $ref) }}">{{ $ref->number }}</a>
                @elseif ($ref instanceof \App\Models\Shipment)<a class="link" href="{{ route('shipments.show', $ref) }}">{{ $ref->reference }}</a>
                @elseif ($ref instanceof \App\Models\StockCount)<a class="link" href="{{ route('stock-counts.show', $ref) }}">{{ $ref->number }}</a>
                @endif
                <span class="block text-slate-500">{{ $m->notes }}</span>
            </td>
            <td class="text-xs">{{ $m->user?->name ?? __('System') }}</td>
        </tr>
    @empty
        <tr><td colspan="8"><x-empty icon="ledger" :title="__('No movements')" /></td></tr>
    @endforelse
    </tbody>
</table>
