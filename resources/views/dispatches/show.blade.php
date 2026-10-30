<x-layouts.app :title="$dispatch->number">
    @php $user = auth()->user(); $status = $dispatch->status->value; @endphp
    <x-page-header :title="$dispatch->number" :subtitle="$dispatch->fromWarehouse->name.' → '.$dispatch->toWarehouse->name.' · '.$dispatch->distance_km.' km'">
        <x-slot:actions>
            <a href="{{ route('dispatches.waybill', $dispatch) }}" target="_blank" class="btn-secondary"><x-icon name="printer" class="size-4" />{{ __('Waybill') }}</a>
            @if ($status === 'draft' && ($user->hasAbility('approve') || $user->hasAbility('logistics.manage')))
                <form method="POST" action="{{ route('dispatches.approve', $dispatch) }}">@csrf<button class="btn-primary"><x-icon name="check" class="size-4" />{{ __('Approve') }}</button></form>
            @endif
            @if ($status === 'approved' && $user->hasAbility('logistics.manage'))
                <form method="POST" action="{{ route('dispatches.depart', $dispatch) }}">@csrf<button class="btn-primary" onclick="return confirm('{{ __('Truck loaded and leaving? Stock will be deducted from origin.') }}')"><x-icon name="truck" class="size-4" />{{ __('Mark departed') }}</button></form>
            @endif
            @if ($status === 'in_transit' && $user->hasAbility('stock.receive'))
                <a href="{{ route('dispatches.receive', $dispatch) }}" class="btn-primary"><x-icon name="check" class="size-4" />{{ __('Confirm delivery') }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Progress --}}
    @php
        $steps = ['draft' => __('Created'), 'approved' => __('Approved'), 'in_transit' => __('In transit'), 'delivered' => __('Delivered')];
        $index = array_search($status, array_keys($steps));
    @endphp
    <div class="card mb-6 p-5">
        @if ($status === 'cancelled')
            <p class="flex items-center gap-2 text-sm font-medium text-red-600"><x-icon name="x" class="size-5" />{{ __('This dispatch was cancelled.') }}</p>
        @else
            <ol class="flex items-center">
                @foreach ($steps as $key => $label)
                    <li class="flex flex-1 items-center {{ $loop->last ? 'flex-none' : '' }}">
                        <span class="flex flex-col items-center">
                            <span @class(['flex size-8 items-center justify-center rounded-full text-xs font-semibold', 'bg-brand-600 text-white' => $loop->index <= $index, 'bg-slate-100 text-slate-400' => $loop->index > $index])>@if ($loop->index < $index)<x-icon name="check" class="size-4" />@else{{ $loop->iteration }}@endif</span>
                            <span class="mt-1 text-xs text-slate-600">{{ $label }}</span>
                        </span>
                        @unless ($loop->last)<span @class(['mx-2 h-0.5 flex-1', 'bg-brand-500' => $loop->index < $index, 'bg-slate-200' => $loop->index >= $index])></span>@endunless
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card overflow-hidden"><div id="tripMap" class="h-80"></div></div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Load') }}</h2><span class="text-sm text-slate-500">{{ number_format($dispatch->totalBags()) }} {{ __('bags') }} · {{ number_format($dispatch->totalTonnes(), 2) }} t</span></div>
                <table class="table">
                    <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Batch') }}</th><th class="num">{{ __('Sent') }}</th><th class="num">{{ __('Received') }}</th><th class="num">{{ __('Damaged') }}</th><th class="num">{{ __('Lost') }}</th></tr></thead>
                    <tbody>
                    @foreach ($dispatch->items as $item)
                        <tr>
                            <td>{{ $item->batch->product->code }}</td>
                            <td class="font-mono text-xs">{{ $item->batch->batch_number }}</td>
                            <td class="num">{{ number_format($item->bags_sent) }}</td>
                            <td class="num">{{ $item->bags_received !== null ? number_format($item->bags_received) : '—' }}</td>
                            <td class="num">{{ $item->bags_damaged ?: '—' }}</td>
                            <td @class(['num font-medium', 'text-red-600' => $item->lostBags() > 0])>{{ $item->lostBags() ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Trip timeline') }}</h2></div>
                <ol class="space-y-4 p-5">
                    @foreach ($dispatch->events as $event)
                        <li class="flex gap-3">
                            <span @class(['mt-1 size-2.5 shrink-0 rounded-full', 'bg-red-500' => in_array($event->event, ['delay', 'breakdown', 'cancelled']), 'bg-brand-500' => ! in_array($event->event, ['delay', 'breakdown', 'cancelled'])])></span>
                            <div class="text-sm">
                                <p class="font-medium text-slate-800">{{ __(ucfirst($event->event)) }} <span class="font-normal text-slate-500">· {{ $event->recorded_at->format('d M Y H:i') }}</span></p>
                                @if ($event->note)<p class="text-slate-600">{{ $event->note }}</p>@endif
                                <p class="text-xs text-slate-400">{{ $event->user?->name ?? __('Driver') }} @if ($event->lat) · {{ number_format($event->lat, 4) }}, {{ number_format($event->lng, 4) }} @endif</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-5 text-sm">
                <dl class="space-y-2.5">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Transporter') }}</dt><dd class="text-right font-medium">@if ($dispatch->transporter)<a class="link" href="{{ route('transporters.show', $dispatch->transporter) }}">{{ $dispatch->transporter->name }}</a>@else — @endif</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Vehicle') }}</dt><dd>{{ $dispatch->vehicle?->plate_number ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Driver') }}</dt><dd class="text-right">{{ $dispatch->driver?->name ?? '—' }}<br><span class="text-xs text-slate-500">{{ $dispatch->driver?->phone }}</span></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Departed') }}</dt><dd>{{ $dispatch->dispatched_at?->format('d M H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">ETA</dt><dd @class(['font-medium', 'text-red-600' => $dispatch->isLate()])>{{ $dispatch->expected_arrival_at?->format('d M H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Delivered') }}</dt><dd>{{ $dispatch->delivered_at?->format('d M H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Last GPS') }}</dt><dd>{{ $dispatch->last_ping_at?->diffForHumans() ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Transport cost') }}</dt><dd>TZS {{ number_format($dispatch->transport_cost) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Created by') }}</dt><dd>{{ $dispatch->creator?->name }}</dd></div>
                </dl>
            </div>

            @if ($dispatch->payment)
                <div class="card p-5 text-sm">
                    <h3 class="card-title mb-3">{{ __('Transporter payment') }}</h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Gross') }}</dt><dd>TZS {{ number_format($dispatch->payment->gross_amount) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Loss deduction') }}</dt><dd class="text-red-600">− TZS {{ number_format($dispatch->payment->loss_deduction) }}</dd></div>
                        <div class="flex justify-between border-t border-slate-100 pt-2 font-semibold"><dt>{{ __('Net payable') }}</dt><dd>TZS {{ number_format($dispatch->payment->net_amount) }}</dd></div>
                    </dl>
                    <x-badge class="mt-3" :color="['pending' => 'amber', 'approved' => 'blue', 'paid' => 'green'][$dispatch->payment->status]">{{ ucfirst($dispatch->payment->status) }}</x-badge>
                </div>
            @endif

            @if ($status === 'delivered')
                <div class="card p-5 text-sm">
                    <h3 class="card-title mb-2">{{ __('Proof of delivery') }}</h3>
                    <p>{{ __('Received by') }} <strong>{{ $dispatch->receiver_name }}</strong></p>
                    @if ($dispatch->pod_notes)<p class="mt-1 text-slate-600">{{ $dispatch->pod_notes }}</p>@endif
                    @if ($dispatch->pod_photo)<img src="{{ asset('storage/'.$dispatch->pod_photo) }}" class="mt-3 rounded-lg border" alt="POD">@endif
                </div>
            @endif

            @if ($status === 'in_transit' && $user->hasAbility('logistics.manage'))
                <form method="POST" action="{{ route('dispatches.track', $dispatch) }}" class="card space-y-3 p-5">
                    @csrf
                    <h3 class="card-title">{{ __('Add tracking update') }}</h3>
                    <select name="event" class="input"><option value="checkpoint">{{ __('Checkpoint') }}</option><option value="rest">{{ __('Rest stop') }}</option><option value="delay">{{ __('Delay') }}</option><option value="breakdown">{{ __('Breakdown') }}</option><option value="arrived">{{ __('Arrived at gate') }}</option></select>
                    <input name="note" class="input" placeholder="{{ __('e.g. Weighbridge Mikese, cleared') }}">
                    <button class="btn-secondary w-full">{{ __('Save update') }}</button>
                    <p class="text-xs text-slate-500">{{ __('Driver link') }}: <a class="link break-all" href="{{ route('trip.show', $dispatch->tracking_token) }}" target="_blank">{{ route('trip.show', $dispatch->tracking_token) }}</a></p>
                </form>
            @endif

            @if (in_array($status, ['draft', 'approved']) && $user->hasAbility('logistics.manage'))
                <form method="POST" action="{{ route('dispatches.cancel', $dispatch) }}" class="card space-y-3 p-5" x-data="{ open: false }">
                    @csrf
                    <button type="button" @click="open = !open" class="text-sm font-medium text-red-600">{{ __('Cancel dispatch…') }}</button>
                    <div x-show="open" x-cloak class="space-y-2"><input name="reason" class="input" placeholder="{{ __('Reason') }}" required><button class="btn-danger w-full">{{ __('Cancel dispatch') }}</button></div>
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const map = tfcMap('tripMap');
            const from = [{{ $dispatch->fromWarehouse->lat ?? -6.8 }}, {{ $dispatch->fromWarehouse->lng ?? 39.28 }}];
            const to = [{{ $dispatch->toWarehouse->lat ?? -6.8 }}, {{ $dispatch->toWarehouse->lng ?? 39.28 }}];
            const trail = {{ \Illuminate\Support\Js::from($dispatch->events->filter(fn ($e) => $e->lat)->sortBy('recorded_at')->map(fn ($e) => [$e->lat, $e->lng])->values()) }};
            L.marker(from, { icon: tfcDot('#7c3aed', 16) }).bindTooltip({{ \Illuminate\Support\Js::from($dispatch->fromWarehouse->name) }}).addTo(map);
            L.marker(to, { icon: tfcDot('#16a34a', 16) }).bindTooltip({{ \Illuminate\Support\Js::from($dispatch->toWarehouse->name) }}).addTo(map);
            L.polyline([from, to], { color: '#94a3b8', dashArray: '6 6', weight: 2 }).addTo(map);
            if (trail.length) L.polyline(trail, { color: '#0284c7', weight: 4 }).addTo(map);
            @if ($dispatch->last_lat && $status === 'in_transit')
                L.marker([{{ $dispatch->last_lat }}, {{ $dispatch->last_lng }}], { icon: tfcTruck('{{ $dispatch->isLate() || $dispatch->isSilent(config('tfc.silent_truck_hours')) ? '#dc2626' : '#0284c7' }}') }).addTo(map);
            @endif
            map.fitBounds(L.latLngBounds([from, to, ...trail]).pad(0.2));
        </script>
    @endpush
</x-layouts.app>
