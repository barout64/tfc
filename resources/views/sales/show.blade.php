<x-layouts.app :title="$sale->number">
    <x-page-header :title="__('Sale :number', ['number' => $sale->number])" :subtitle="$sale->sold_at->format('d M Y H:i').' · '.$sale->warehouse->name">
        <x-slot:actions><button onclick="window.print()" class="btn-secondary"><x-icon name="printer" class="size-4" />{{ __('Print receipt') }}</button></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="grid gap-4 border-b border-slate-100 p-5 text-sm sm:grid-cols-3">
                <div><p class="text-xs text-slate-500">{{ __('Customer') }}</p><p class="font-medium">@if ($sale->farmer)<a class="link" href="{{ route('farmers.show', $sale->farmer) }}">{{ $sale->customerName() }}</a>@elseif ($sale->agroDealer)<a class="link" href="{{ route('dealers.show', $sale->agroDealer) }}">{{ $sale->customerName() }}</a>@else{{ $sale->customerName() }}@endif</p><p class="text-xs text-slate-500">{{ $sale->farmer?->farmer_number ?? $sale->agroDealer?->dealer_number }}</p></div>
                <div><p class="text-xs text-slate-500">{{ __('Sold by') }}</p><p>{{ $sale->seller?->name }}</p></div>
                <div><p class="text-xs text-slate-500">{{ __('Region') }}</p><p>{{ $sale->warehouse->region->name }}</p></div>
            </div>
            <table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Batch') }}</th><th class="num">{{ __('Bags') }}</th><th class="num">{{ __('Price') }}</th><th class="num">{{ __('Subsidy / bag') }}</th><th class="num">{{ __('Total') }}</th></tr></thead>
                <tbody>
                @foreach ($sale->items as $item)
                    <tr><td>{{ $item->product->name }}</td><td class="font-mono text-xs">{{ $item->batch->batch_number }}</td><td class="num">{{ number_format($item->bags) }}</td><td class="num">{{ number_format($item->unit_price) }}</td><td class="num text-slate-500">{{ number_format($item->subsidy_per_bag) }}</td><td class="num font-medium">{{ number_format($item->line_total) }}</td></tr>
                @endforeach
                <tr class="font-semibold"><td colspan="2">{{ __('Total') }}</td><td class="num">{{ number_format($sale->total_bags) }}</td><td></td><td class="num text-slate-500">{{ number_format($sale->subsidy_amount) }}</td><td class="num">TZS {{ number_format($sale->total_amount) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card p-5">
            <h2 class="card-title">{{ __('Payment') }}</h2>
            <p class="mt-2"><x-badge :color="['paid' => 'green', 'pending' => 'amber', 'partial' => 'blue', 'credit' => 'violet'][$sale->payment_status] ?? 'gray'" class="text-sm">{{ __(ucfirst($sale->payment_status)) }}</x-badge> <span class="text-sm text-slate-500">{{ $sale->payment_method->label() }}</span></p>
            @if ($sale->control_number)
                <div class="mt-4 rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">{{ __('GePG control number') }}</p><p class="font-mono text-lg font-semibold tracking-wider">{{ $sale->control_number }}</p></div>
            @endif
            <ul class="mt-4 divide-y divide-slate-100 text-sm">
                @foreach ($sale->payments as $payment)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <span><span class="font-medium">TZS {{ number_format($payment->amount) }}</span><span class="block text-xs text-slate-500">{{ $payment->method->label() }} · {{ $payment->reference }}</span></span>
                        @if ($payment->status === 'pending' && (auth()->user()->hasAbility('finance.manage') || auth()->user()->hasAbility('sales.manage')))
                            <form method="POST" action="{{ route('payments.confirm', $payment) }}">@csrf<button class="btn-secondary btn-sm">{{ __('Confirm') }}</button></form>
                        @else
                            <x-badge :color="$payment->status === 'confirmed' ? 'green' : ($payment->status === 'failed' ? 'red' : 'amber')">{{ __(ucfirst($payment->status)) }}</x-badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-layouts.app>
