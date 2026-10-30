<x-layouts.portal :title="__('Transporter portal')" :subtitle="$transporter->name">
    <h1 class="text-2xl font-semibold">{{ $transporter->name }}</h1>
    <p class="mb-6 text-sm text-slate-500">{{ $transporter->vehicles->count() }} {{ __('vehicles') }} · {{ $transporter->drivers->count() }} {{ __('drivers') }} · TZS {{ number_format($transporter->rate_per_tonne_km) }} / t·km</p>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Active trips')" :value="$trips->where('status.value', 'in_transit')->count()" icon="truck" />
        <x-stat :label="__('Awaiting payment')" :value="'TZS '.number_format($payments->whereIn('status', ['pending', 'approved'])->sum('net_amount'))" icon="clock" tone="amber" />
        <x-stat :label="__('Paid')" :value="'TZS '.number_format($payments->where('status', 'paid')->sum('net_amount'))" icon="coins" tone="blue" />
    </div>
    <div class="card mt-6 overflow-x-auto">
        <div class="card-header"><h2 class="card-title">{{ __('My trips') }}</h2></div>
        <table class="table"><thead><tr><th>{{ __('Trip') }}</th><th>{{ __('Route') }}</th><th>{{ __('Vehicle / driver') }}</th><th class="num">t</th><th>ETA</th><th>{{ __('Status') }}</th><th></th></tr></thead><tbody>
        @forelse ($trips as $d)
            <tr>
                <td class="font-medium">{{ $d->number }}</td>
                <td class="text-xs">{{ $d->fromWarehouse->name }} → {{ $d->toWarehouse->name }}<br>{{ $d->distance_km }} km</td>
                <td class="text-xs">{{ $d->vehicle?->plate_number }}<br>{{ $d->driver?->name }}</td>
                <td class="num">{{ number_format($d->totalTonnes(), 1) }}</td>
                <td class="text-xs">{{ $d->expected_arrival_at?->format('d M H:i') ?? '—' }}</td>
                <td><x-badge :color="$d->status->color()">{{ $d->status->label() }}</x-badge></td>
                <td>@if ($d->status->value === 'in_transit')<a href="{{ route('trip.show', $d->tracking_token) }}" class="btn-secondary btn-sm">{{ __('Driver page') }}</a>@endif</td>
            </tr>
        @empty<tr><td colspan="7"><x-empty icon="truck" :title="__('No trips assigned')" /></td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="card mt-6 overflow-x-auto">
        <div class="card-header"><h2 class="card-title">{{ __('Payments') }}</h2></div>
        <table class="table"><thead><tr><th>{{ __('Trip') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('Loss deduction') }}</th><th class="num">{{ __('Net') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reference') }}</th></tr></thead><tbody>
        @forelse ($payments as $p)<tr><td>{{ $p->dispatch->number }}</td><td class="num">{{ number_format($p->gross_amount) }}</td><td class="num text-red-600">{{ $p->loss_deduction > 0 ? '−'.number_format($p->loss_deduction) : '—' }}</td><td class="num font-semibold">{{ number_format($p->net_amount) }}</td><td><x-badge :color="['pending' => 'amber', 'approved' => 'blue', 'paid' => 'green'][$p->status]">{{ ucfirst($p->status) }}</x-badge></td><td class="text-xs">{{ $p->payment_reference }}</td></tr>
        @empty<tr><td colspan="6" class="text-center text-slate-500">{{ __('No payments yet') }}</td></tr>@endforelse
        </tbody></table>
    </div>
</x-layouts.portal>
