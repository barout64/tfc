<x-layouts.portal :title="__('Dealer portal')" :subtitle="$dealer->business_name">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-semibold">{{ __('Karibu, :name', ['name' => $dealer->owner_name]) }}</h1><p class="text-sm text-slate-500">{{ $dealer->dealer_number }} · {{ __('TFRA') }} {{ $dealer->tfra_license_no }} · {{ __('expires') }} {{ $dealer->license_expiry?->format('d M Y') }}</p></div>
        <a href="{{ route('dealer.sell') }}" class="btn-primary py-2.5"><x-icon name="cart" class="size-4" />{{ __('Sell to farmer') }}</a>
    </div>
    @if ($dealer->isLicenseExpired())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ __('Your TFRA licence has expired. Renew it to continue buying from TFC.') }}</div>
    @endif
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Bags in my shop')" :value="number_format($stock->sum('bags'))" icon="cube" />
        <x-stat :label="__('Farmers served')" :value="number_format($farmersServed)" icon="users" tone="blue" />
        <x-stat :label="__('Bags sold to farmers')" :value="number_format($sales->sum('total_bags'))" icon="cart" tone="amber" />
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('My stock') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Product') }}</th><th>{{ __('Batch') }}</th><th class="num">{{ __('Bags') }}</th></tr></thead><tbody>
            @forelse ($stock as $b)<tr><td>{{ $b->batch->product->code }}</td><td class="font-mono text-xs">{{ $b->batch->batch_number }}</td><td class="num font-medium">{{ number_format($b->bags) }}</td></tr>
            @empty<tr><td colspan="3"><x-empty icon="cube" :title="__('No stock')" :text="__('Buy from your nearest TFC warehouse; stock appears here automatically.')" /></td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Recent sales to farmers') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Farmer') }}</th><th>{{ __('Products') }}</th><th class="num">{{ __('Bags') }}</th><th>{{ __('Date') }}</th></tr></thead><tbody>
            @forelse ($sales as $s)<tr><td>{{ $s->farmer?->fullName() }}</td><td class="text-xs">{{ $s->items->pluck('product.code')->unique()->implode(', ') }}</td><td class="num">{{ $s->total_bags }}</td><td class="text-xs">{{ $s->sold_at->format('d M H:i') }}</td></tr>
            @empty<tr><td colspan="4" class="text-center text-slate-500">{{ __('No sales yet') }}</td></tr>@endforelse
            </tbody></table>
        </div>
    </div>
    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">{{ __('My purchases from TFC') }}</h2></div>
        <table class="table"><thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Warehouse') }}</th><th class="num">{{ __('Bags') }}</th><th class="num">TZS</th><th>{{ __('Payment') }}</th></tr></thead><tbody>
        @forelse ($purchases as $s)<tr><td>{{ $s->number }}</td><td class="text-xs">{{ $s->sold_at->format('d M Y') }}</td><td>{{ $s->warehouse->name }}</td><td class="num">{{ number_format($s->total_bags) }}</td><td class="num">{{ number_format($s->total_amount) }}</td><td><x-badge>{{ $s->payment_status }}</x-badge></td></tr>
        @empty<tr><td colspan="6" class="text-center text-slate-500">{{ __('No purchases yet') }}</td></tr>@endforelse
        </tbody></table>
    </div>
</x-layouts.portal>
