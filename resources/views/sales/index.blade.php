<x-layouts.app :title="__('Sales')">
    <x-page-header :title="__('Sales')" :subtitle="__('All sales from TFC warehouses and sale points.')">
        <x-slot:actions>
            <a href="{{ route('reports.show', ['sales-register', 'export' => 'csv', 'from' => request('from'), 'to' => request('to')]) }}" class="btn-secondary"><x-icon name="download" class="size-4" />{{ __('Export') }}</a>
            @if (auth()->user()->hasAbility('sales.manage'))<a href="{{ route('sales.create') }}" class="btn-primary"><x-icon name="cart" class="size-4" />{{ __('New sale') }}</a>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Sales')" :value="number_format($totals->count)" icon="cart" />
        <x-stat :label="__('Bags')" :value="number_format($totals->bags)" icon="cube" tone="blue" />
        <x-stat :label="__('Value')" :value="'TZS '.number_format($totals->amount)" icon="coins" tone="amber" />
        <x-stat :label="__('Subsidy')" :value="'TZS '.number_format($totals->subsidy)" icon="shield" tone="violet" />
    </div>

    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-3 xl:grid-cols-7">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Sale number…') }}" class="input">
        <select name="warehouse" class="input"><option value="">{{ __('All points of sale') }}</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected(request('warehouse') == $w->id)>{{ $w->name }}</option>@endforeach</select>
        <select name="customer_type" class="input"><option value="">{{ __('All customers') }}</option>@foreach (['farmer' => __('Farmers'), 'agro_dealer' => __('Agro-dealers'), 'cooperative' => __('Cooperatives')] as $v => $l)<option value="{{ $v }}" @selected(request('customer_type') === $v)>{{ $l }}</option>@endforeach</select>
        <select name="payment_status" class="input"><option value="">{{ __('Any payment') }}</option>@foreach (['paid', 'pending', 'partial', 'credit'] as $s)<option value="{{ $s }}" @selected(request('payment_status') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ request('from') }}" class="input">
        <input type="date" name="to" value="{{ request('to') }}" class="input">
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Filter') }}</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Sale') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Point of sale') }}</th><th class="num">{{ __('Bags') }}</th><th class="num">{{ __('Amount') }}</th><th>{{ __('Payment') }}</th></tr></thead>
            <tbody>
            @forelse ($sales as $s)
                <tr>
                    <td><a href="{{ route('sales.show', $s) }}" class="link">{{ $s->number }}</a></td>
                    <td class="text-xs">{{ $s->sold_at->format('d M Y H:i') }}</td>
                    <td>{{ $s->customerName() }}<p class="text-xs text-slate-500">{{ __(ucwords(str_replace('_', ' ', $s->customer_type))) }}</p></td>
                    <td>{{ $s->warehouse->name }}</td>
                    <td class="num">{{ number_format($s->total_bags) }}</td>
                    <td class="num">{{ number_format($s->total_amount) }}</td>
                    <td><x-badge :color="['paid' => 'green', 'pending' => 'amber', 'partial' => 'blue', 'credit' => 'violet'][$s->payment_status] ?? 'gray'">{{ __(ucfirst($s->payment_status)) }}</x-badge><p class="text-xs text-slate-500">{{ $s->payment_method->label() }}</p></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="cart" :title="__('No sales found')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $sales->links() }}</div>
</x-layouts.app>
