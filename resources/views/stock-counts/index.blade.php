<x-layouts.app :title="__('Stock counts')">
    <x-page-header :title="__('Physical stock counts')" :subtitle="__('Count bags in the warehouse; differences are applied only after approval.')">
        <x-slot:actions>
            @if (auth()->user()->hasAbility('stock.manage'))
                <a href="{{ route('stock-counts.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" />{{ __('New count') }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Date') }}</th><th>{{ __('Counted by') }}</th><th class="num">{{ __('Variance (bags)') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($counts as $c)
                @php $variance = $c->items->sum(fn ($i) => $i->variance()); @endphp
                <tr>
                    <td><a href="{{ route('stock-counts.show', $c) }}" class="link">{{ $c->number }}</a></td>
                    <td>{{ $c->warehouse->name }}</td>
                    <td>{{ $c->counted_on->format('d M Y') }}</td>
                    <td>{{ $c->counter?->name }}</td>
                    <td @class(['num font-medium', 'text-red-600' => $variance < 0, 'text-emerald-700' => $variance > 0])>{{ $variance > 0 ? '+' : '' }}{{ number_format($variance) }}</td>
                    <td><x-badge :color="['submitted' => 'amber', 'approved' => 'green', 'rejected' => 'red'][$c->status] ?? 'gray'">{{ ucfirst($c->status) }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="clipboard" :title="__('No stock counts yet')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $counts->links() }}</div>
</x-layouts.app>
