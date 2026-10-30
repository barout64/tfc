<x-layouts.app :title="$transporter->name">
    <x-page-header :title="$transporter->name" :subtitle="__('TIN').' '.($transporter->tin ?: '—').' · '.$transporter->contact_person.' · '.$transporter->phone">
        <x-slot:actions>
            @if (auth()->user()->hasAbility('logistics.manage'))<a href="{{ route('transporters.edit', $transporter) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" />{{ __('Edit') }}</a>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Trips this season')" :value="$performance->trips ?? 0" icon="truck" />
        <x-stat :label="__('Tonnes moved')" :value="number_format($performance->tonnes ?? 0, 1)" icon="cube" tone="blue" />
        <x-stat :label="__('On-time delivery')" :value="($performance->on_time_percent ?? 0).'%'" icon="clock" tone="amber" />
        <x-stat :label="__('Bags lost')" :value="number_format($performance->lost_bags ?? 0)" :sub="($performance->loss_rate ?? 0).'% '.__('loss rate')" icon="alert" tone="red" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Vehicles') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Plate') }}</th><th>{{ __('Type') }}</th><th class="num">{{ __('Capacity') }}</th></tr></thead><tbody>
            @forelse ($transporter->vehicles as $v)<tr><td class="font-mono">{{ $v->plate_number }}</td><td>{{ ucfirst($v->type) }}</td><td class="num">{{ number_format($v->capacity_tonnes) }} t</td></tr>@empty<tr><td colspan="3" class="text-center text-slate-500">{{ __('No vehicles') }}</td></tr>@endforelse
            </tbody></table>
            @if (auth()->user()->hasAbility('logistics.manage'))
                <form method="POST" action="{{ route('transporters.vehicles.store', $transporter) }}" class="grid grid-cols-4 gap-2 border-t border-slate-100 p-4">
                    @csrf
                    <input name="plate_number" placeholder="T 123 ABC" class="input" required>
                    <select name="type" class="input"><option value="truck">{{ __('Truck') }}</option><option value="trailer">{{ __('Trailer') }}</option><option value="wagon">{{ __('Wagon') }}</option></select>
                    <input name="capacity_tonnes" type="number" step="0.1" placeholder="{{ __('Tonnes') }}" class="input" required>
                    <button class="btn-secondary">{{ __('Add') }}</button>
                </form>
            @endif
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Drivers') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Name') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Licence') }}</th></tr></thead><tbody>
            @forelse ($transporter->drivers as $d)<tr><td>{{ $d->name }}</td><td>{{ $d->phone }}</td><td class="text-xs">{{ $d->license_number }}</td></tr>@empty<tr><td colspan="3" class="text-center text-slate-500">{{ __('No drivers') }}</td></tr>@endforelse
            </tbody></table>
            @if (auth()->user()->hasAbility('logistics.manage'))
                <form method="POST" action="{{ route('transporters.drivers.store', $transporter) }}" class="grid grid-cols-4 gap-2 border-t border-slate-100 p-4">
                    @csrf
                    <input name="name" placeholder="{{ __('Name') }}" class="input" required>
                    <input name="phone" placeholder="07XXXXXXXX" class="input" required>
                    <input name="license_number" placeholder="{{ __('Licence no.') }}" class="input">
                    <button class="btn-secondary">{{ __('Add') }}</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Recent trips') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Dispatch') }}</th><th>{{ __('Route') }}</th><th class="num">t</th><th>{{ __('Status') }}</th></tr></thead><tbody>
            @forelse ($trips as $d)
                <tr><td><a class="link" href="{{ route('dispatches.show', $d) }}">{{ $d->number }}</a></td><td class="text-xs">{{ $d->fromWarehouse->name }} → {{ $d->toWarehouse->name }}</td><td class="num">{{ number_format($d->totalTonnes(), 1) }}</td><td><x-badge :color="$d->status->color()">{{ $d->status->label() }}</x-badge></td></tr>
            @empty<tr><td colspan="4" class="text-center text-slate-500">{{ __('No trips yet') }}</td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Payments') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Dispatch') }}</th><th class="num">{{ __('Net') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
            @forelse ($payments as $p)
                <tr><td>{{ $p->dispatch->number }}</td><td class="num">TZS {{ number_format($p->net_amount) }}</td><td><x-badge :color="['pending' => 'amber', 'approved' => 'blue', 'paid' => 'green'][$p->status]">{{ ucfirst($p->status) }}</x-badge></td></tr>
            @empty<tr><td colspan="3" class="text-center text-slate-500">{{ __('No payments yet') }}</td></tr>@endforelse
            </tbody></table>
        </div>
    </div>
</x-layouts.app>
