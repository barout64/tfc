<x-layouts.app :title="__('Transport payments')">
    <x-page-header :title="__('Transporter payments')" :subtitle="__('Calculated automatically on delivery: tonnes × km × rate, minus penalties for lost or damaged bags.')" />
    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        @foreach (['pending' => ['amber', 'clock'], 'approved' => ['blue', 'check'], 'paid' => ['brand', 'coins']] as $status => [$tone, $icon])
            <x-stat :label="__(ucfirst($status))" :value="'TZS '.number_format($totals[$status]->amount ?? 0)" :sub="($totals[$status]->total ?? 0).' '.__('trips')" :icon="$icon" :tone="$tone" :href="route('transporter-payments.index', ['status' => $status])" />
        @endforeach
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Transporter') }}</th><th>{{ __('Dispatch') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('Deduction') }}</th><th class="num">{{ __('Net') }}</th><th>{{ __('Status') }}</th><th class="w-72"></th></tr></thead>
            <tbody>
            @forelse ($payments as $p)
                <tr>
                    <td>{{ $p->transporter->name }}</td>
                    <td><a href="{{ route('dispatches.show', $p->dispatch) }}" class="link">{{ $p->dispatch->number }}</a><p class="text-xs text-slate-500">{{ $p->dispatch->fromWarehouse->name }} → {{ $p->dispatch->toWarehouse->name }}</p></td>
                    <td class="num">{{ number_format($p->gross_amount) }}</td>
                    <td class="num text-red-600">{{ $p->loss_deduction > 0 ? '−'.number_format($p->loss_deduction) : '—' }}</td>
                    <td class="num font-semibold">{{ number_format($p->net_amount) }}</td>
                    <td><x-badge :color="['pending' => 'amber', 'approved' => 'blue', 'paid' => 'green'][$p->status]">{{ ucfirst($p->status) }}</x-badge>@if ($p->payment_reference)<p class="text-xs text-slate-500">{{ $p->payment_reference }}</p>@endif</td>
                    <td>
                        @if (auth()->user()->hasAbility('finance.manage'))
                            @if ($p->status === 'pending')
                                <form method="POST" action="{{ route('transporter-payments.update', $p) }}">@csrf<input type="hidden" name="action" value="approve"><button class="btn-secondary btn-sm">{{ __('Approve') }}</button></form>
                            @elseif ($p->status === 'approved')
                                <form method="POST" action="{{ route('transporter-payments.update', $p) }}" class="flex gap-2">@csrf<input type="hidden" name="action" value="pay"><input name="payment_reference" placeholder="{{ __('Payment ref.') }}" class="input py-1 text-xs" required><button class="btn-primary btn-sm">{{ __('Paid') }}</button></form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="coins" :title="__('No payments')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
