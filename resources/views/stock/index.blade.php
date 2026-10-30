<x-layouts.app :title="__('Stock on hand')">
    <x-page-header :title="__('Stock on hand')" :subtitle="__('Stock is stored as whole bags; tonnes are calculated, so totals always add up.')">
        <x-slot:actions>
            <a href="{{ route('reports.show', ['stock-position', 'export' => 'csv']) }}" class="btn-secondary"><x-icon name="download" class="size-4" />{{ __('Export CSV') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($summary as $p)
            <div class="card p-4">
                <p class="flex items-center gap-2 text-xs font-semibold text-slate-500"><span class="size-2.5 rounded-full" style="background: {{ $p->color }}"></span>{{ $p->code }}</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($p->tonnes, 1) }} t</p>
                <p class="text-xs text-slate-500 tabular-nums">{{ number_format($p->bags) }} {{ __('bags') }}</p>
            </div>
        @endforeach
    </div>

    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-4">
        <select name="warehouse" class="input"><option value="">{{ __('All warehouses') }}</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected(request('warehouse') == $w->id)>{{ $w->name }}</option>@endforeach</select>
        <select name="product" class="input"><option value="">{{ __('All products') }}</option>@foreach ($products as $p)<option value="{{ $p->id }}" @selected(request('product') == $p->id)>{{ $p->code }}</option>@endforeach</select>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="expiring" value="1" @checked(request('expiring')) class="rounded border-slate-300 text-brand-600"> {{ __('Expiring within :days days', ['days' => config('tfc.expiry_warning_days')]) }}</label>
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Filter') }}</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Warehouse') }}</th><th>{{ __('Region') }}</th><th>{{ __('Product') }}</th><th>{{ __('Package') }}</th><th>{{ __('Batch') }}</th><th>{{ __('Expires') }}</th><th class="num">{{ __('Bags') }}</th><th class="num">{{ __('Tonnes') }}</th></tr></thead>
            <tbody>
            @forelse ($balances as $b)
                <tr>
                    <td><a href="{{ route('warehouses.show', $b->warehouse) }}" class="link">{{ $b->warehouse->name }}</a></td>
                    <td>{{ $b->warehouse->region->name }}</td>
                    <td><span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full" style="background: {{ $b->batch->product->color }}"></span>{{ $b->batch->product->code }}</span></td>
                    <td>{{ $b->batch->product->package_kg }} kg</td>
                    <td class="font-mono text-xs">{{ $b->batch->batch_number }}</td>
                    <td>@if ($b->batch->expires_on)<span @class(['text-red-600 font-medium' => $b->batch->isExpiringSoon(config('tfc.expiry_warning_days'))])>{{ $b->batch->expires_on->format('d M Y') }}</span>@else — @endif</td>
                    <td class="num">{{ number_format($b->bags) }}</td>
                    <td class="num font-medium">{{ number_format($b->batch->product->bagsToTonnes($b->bags), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="cube" :title="__('No stock matches your filters')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $balances->links() }}</div>
</x-layouts.app>
