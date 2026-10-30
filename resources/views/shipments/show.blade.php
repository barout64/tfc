<x-layouts.app :title="$shipment->reference">
    <x-page-header :title="($shipment->vessel_name ?: __('Shipment')).' · '.$shipment->reference" :subtitle="$shipment->product->name.' · '.number_format($shipment->ordered_tonnes).' t · '.($shipment->supplier?->name ?? '')">
        <x-slot:actions><x-badge :color="$shipment->status->color()" class="text-sm">{{ $shipment->status->label() }}</x-badge></x-slot:actions>
    </x-page-header>

    @php
        $steps = ['ordered', 'in_transit', 'at_port', 'clearing', 'offloading', 'completed'];
        $current = array_search($shipment->status->value, $steps);
    @endphp
    <div class="card mb-6 p-5">
        <ol class="grid grid-cols-3 gap-y-4 sm:grid-cols-6">
            @foreach ($steps as $i => $step)
                <li class="flex flex-col items-center text-center">
                    <span @class(['flex size-8 items-center justify-center rounded-full text-xs font-semibold', 'bg-brand-600 text-white' => $current !== false && $i <= $current, 'bg-slate-100 text-slate-400' => $current === false || $i > $current])>@if ($current !== false && $i < $current)<x-icon name="check" class="size-4" />@else{{ $i + 1 }}@endif</span>
                    <span class="mt-1.5 text-xs text-slate-600">{{ \App\Enums\ShipmentStatus::from($step)->label() }}</span>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat :label="__('Received')" :value="number_format($shipment->receivedTonnes(), 1).' t'" :sub="number_format($shipment->bags_received).' '.__('bags')" icon="check" />
                <x-stat :label="__('Damaged')" :value="number_format($shipment->bags_damaged)" :sub="__('bags')" icon="alert" tone="red" />
                <x-stat :label="__('Outstanding')" :value="number_format(max(0, $shipment->ordered_tonnes - $shipment->receivedTonnes()), 1).' t'" icon="clock" tone="amber" />
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Receipts into warehouses') }}</h2></div>
                <table class="table">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Batch') }}</th><th class="num">{{ __('Bags') }}</th><th>{{ __('By') }}</th></tr></thead>
                    <tbody>
                    @forelse ($receipts as $r)
                        <tr><td>{{ $r->created_at->format('d M Y H:i') }}</td><td>{{ $r->warehouse->name }}</td><td class="font-mono text-xs">{{ $r->batch->batch_number }}</td><td class="num">{{ number_format($r->bags) }}</td><td>{{ $r->user?->name }}</td></tr>
                    @empty
                        <tr><td colspan="5"><x-empty icon="ship" :title="__('Nothing offloaded yet')" /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-5 text-sm">
                <dl class="space-y-2">
                    <div class="flex justify-between"><dt class="text-slate-500">ETA</dt><dd @class(['font-medium', 'text-red-600' => $shipment->isDelayed()])>{{ $shipment->eta?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Arrived') }}</dt><dd>{{ $shipment->arrived_on?->format('d M Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Port') }}</dt><dd>{{ $shipment->port }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Cost / tonne') }}</dt><dd>{{ $shipment->unit_cost_per_tonne ? 'TZS '.number_format($shipment->unit_cost_per_tonne) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Season') }}</dt><dd>{{ $shipment->season->name }}</dd></div>
                </dl>
                @if ($shipment->notes)<p class="mt-3 border-t border-slate-100 pt-3 text-slate-600">{{ $shipment->notes }}</p>@endif
            </div>

            @unless (in_array($shipment->status->value, ['completed', 'cancelled']))
                @if (auth()->user()->hasAbility('logistics.manage'))
                    <form method="POST" action="{{ route('shipments.status', $shipment) }}" class="card space-y-3 p-5">
                        @csrf
                        <h3 class="card-title">{{ __('Update status') }}</h3>
                        <select name="status" class="input">@foreach (collect($statuses)->except('completed') as $v => $l)<option value="{{ $v }}" @selected($shipment->status->value === $v)>{{ $l }}</option>@endforeach</select>
                        <button class="btn-secondary w-full">{{ __('Update') }}</button>
                    </form>
                @endif
                @if (auth()->user()->hasAbility('stock.receive'))
                    <form method="POST" action="{{ route('shipments.receive', $shipment) }}" class="card space-y-3 p-5">
                        @csrf
                        <h3 class="card-title">{{ __('Record offloading') }}</h3>
                        <x-field :label="__('Into warehouse')" name="warehouse_id" :value="$shipment->receiving_warehouse_id" :options="$warehouses->pluck('name', 'id')" required />
                        <div class="grid grid-cols-2 gap-3">
                            <x-field :label="__('Good bags')" name="good_bags" type="number" min="0" required />
                            <x-field :label="__('Damaged bags')" name="damaged_bags" type="number" min="0" value="0" required />
                        </div>
                        <x-field :label="__('Batch number')" name="batch_number" :value="$shipment->batch_number ?? strtoupper(str_replace(' ', '-', $shipment->vessel_name ?? $shipment->reference))" required />
                        <x-field :label="__('Expiry date')" name="expires_on" type="date" :value="$shipment->expires_on" required />
                        <button class="btn-primary w-full">{{ __('Add to stock') }}</button>
                    </form>
                @endif
                @if (auth()->user()->hasAbility('logistics.manage') && $shipment->bags_received > 0)
                    <form method="POST" action="{{ route('shipments.complete', $shipment) }}">@csrf<button class="btn-secondary w-full" onclick="return confirm('{{ __('Close this shipment?') }}')">{{ __('Mark shipment completed') }}</button></form>
                @endif
            @endunless
        </div>
    </div>
</x-layouts.app>
