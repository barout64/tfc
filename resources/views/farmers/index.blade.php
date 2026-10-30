<x-layouts.app :title="__('Farmers')">
    <x-page-header :title="__('Farmers')" :subtitle="__('Registered farmers, their subsidy allowance and purchases.')">
        <x-slot:actions>
            @if (auth()->user()->hasAbility('farmers.manage'))<a href="{{ route('farmers.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" />{{ __('Register farmer') }}</a>@endif
        </x-slot:actions>
    </x-page-header>
    <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Registered')" :value="number_format($stats['total'])" icon="users" />
        <x-stat :label="__('Served this season')" :value="number_format($stats['served'])" :sub="$stats['total'] ? round($stats['served'] / $stats['total'] * 100).'% '.__('reach') : null" icon="cart" tone="amber" />
        <x-stat :label="__('Women farmers')" :value="number_format($stats['female'])" :sub="$stats['total'] ? round($stats['female'] / $stats['total'] * 100).'%' : null" icon="group" tone="violet" />
        <x-stat :label="__('Registered via USSD')" :value="number_format($stats['ussd'])" :sub="config('tfc.ussd_code')" icon="phone" tone="blue" />
    </div>
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-5">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Name, phone, NIDA or farmer no.') }}" class="input sm:col-span-2">
        <select name="region" class="input"><option value="">{{ __('All regions') }}</option>@foreach ($regions as $r)<option value="{{ $r->id }}" @selected(request('region') == $r->id)>{{ $r->name }}</option>@endforeach</select>
        <select name="crop" class="input"><option value="">{{ __('All crops') }}</option>@foreach ($crops as $c)<option value="{{ $c->id }}" @selected(request('crop') == $c->id)>{{ $c->name }}</option>@endforeach</select>
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Search') }}</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Farmer') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Location') }}</th><th>{{ __('Crop') }}</th><th class="num">{{ __('Acres') }}</th><th>{{ __('Cooperative') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($farmers as $f)
                <tr>
                    <td><a href="{{ route('farmers.show', $f) }}" class="link">{{ $f->fullName() }}</a><p class="text-xs text-slate-500">{{ $f->farmer_number }}</p></td>
                    <td class="text-xs">{{ $f->phone }}</td>
                    <td>{{ $f->region->name }}<p class="text-xs text-slate-500">{{ $f->village }}</p></td>
                    <td>{{ $f->mainCrop?->name }}</td>
                    <td class="num">{{ $f->farm_size_acres + 0 }}</td>
                    <td class="text-xs">{{ $f->cooperative?->name ?? '—' }}</td>
                    <td><x-badge>{{ strtoupper($f->registered_via) }}</x-badge></td>
                    <td><x-badge :color="$f->status === 'active' ? 'green' : 'red'">{{ __(ucfirst($f->status)) }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="users" :title="__('No farmers found')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $farmers->links() }}</div>
</x-layouts.app>
