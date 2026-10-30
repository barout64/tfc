<x-layouts.app :title="__('New stock count')">
    <x-page-header :title="__('New stock count')" />
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-64 flex-1"><label class="label">{{ __('Warehouse') }}</label>
            <select name="warehouse" class="input" onchange="this.form.submit()"><option value="">{{ __('— Select —') }}</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected($warehouse?->id === $w->id)>{{ $w->name }}</option>@endforeach</select>
        </div>
    </form>

    @if ($warehouse)
        <form method="POST" action="{{ route('stock-counts.store') }}" class="card">
            @csrf
            <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">
            <div class="grid gap-4 p-5 sm:grid-cols-3">
                <x-field :label="__('Count date')" name="counted_on" type="date" :value="now()->toDateString()" required />
                <x-field :label="__('Notes')" name="notes" class="sm:col-span-2" />
            </div>
            <table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Batch') }}</th><th class="num">{{ __('System bags') }}</th><th class="w-48">{{ __('Counted bags') }}</th></tr></thead>
                <tbody>
                @forelse ($balances as $b)
                    <tr>
                        <td>{{ $b->batch->product->code }}</td>
                        <td class="font-mono text-xs">{{ $b->batch->batch_number }}</td>
                        <td class="num">{{ number_format($b->bags) }}</td>
                        <td><input type="number" min="0" name="counts[{{ $b->stock_batch_id }}]" value="{{ old('counts.'.$b->stock_batch_id, $b->bags) }}" class="input text-right" required></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty icon="cube" :title="__('No stock recorded at this warehouse')" /></td></tr>
                @endforelse
                </tbody>
            </table>
            @if ($balances->isNotEmpty())
                <div class="flex justify-end p-5"><button class="btn-primary">{{ __('Submit for approval') }}</button></div>
            @endif
        </form>
    @endif
</x-layouts.app>
