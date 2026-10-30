<x-layouts.app :title="$count->number">
    <x-page-header :title="__('Stock count :number', ['number' => $count->number])" :subtitle="$count->warehouse->name.' · '.$count->counted_on->format('d M Y').' · '.__('by').' '.$count->counter?->name" />
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Batch') }}</th><th class="num">{{ __('System') }}</th><th class="num">{{ __('Counted') }}</th><th class="num">{{ __('Variance') }}</th></tr></thead>
            <tbody>
            @foreach ($count->items as $item)
                <tr>
                    <td>{{ $item->batch->product->code }}</td>
                    <td class="font-mono text-xs">{{ $item->batch->batch_number }}</td>
                    <td class="num">{{ number_format($item->system_bags) }}</td>
                    <td class="num">{{ number_format($item->counted_bags) }}</td>
                    <td @class(['num font-semibold', 'text-red-600' => $item->variance() < 0, 'text-emerald-700' => $item->variance() > 0])>{{ $item->variance() > 0 ? '+' : '' }}{{ number_format($item->variance()) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">{{ $count->notes }}</p>
        @if ($count->status === 'submitted' && auth()->user()->hasAbility('approve'))
            <form method="POST" action="{{ route('stock-counts.approve', $count) }}" class="flex gap-2">
                @csrf
                <button name="decision" value="reject" class="btn-secondary">{{ __('Reject') }}</button>
                <button name="decision" value="approve" class="btn-primary" onclick="return confirm('{{ __('Apply these differences to stock?') }}')">{{ __('Approve & adjust stock') }}</button>
            </form>
        @else
            <x-badge :color="['submitted' => 'amber', 'approved' => 'green', 'rejected' => 'red'][$count->status] ?? 'gray'">{{ ucfirst($count->status) }} @if ($count->approver) · {{ $count->approver->name }} @endif</x-badge>
        @endif
    </div>
</x-layouts.app>
