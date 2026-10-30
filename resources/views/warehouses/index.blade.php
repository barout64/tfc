<x-layouts.app :title="__('Warehouses')">
    <x-page-header :title="__('Warehouses & sale points')" :subtitle="__('Every TFC storage location, its capacity and current stock.')">
        <x-slot:actions>
            @if (auth()->user()->hasAbility('warehouses.manage'))
                <a href="{{ route('warehouses.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" />{{ __('Add warehouse') }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-4">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Search name or code…') }}" class="input">
        <select name="type" class="input"><option value="">{{ __('All types') }}</option>@foreach ($types as $v => $l)<option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>@endforeach</select>
        <select name="region" class="input"><option value="">{{ __('All regions') }}</option>@foreach ($regions as $r)<option value="{{ $r->id }}" @selected(request('region') == $r->id)>{{ $r->name }}</option>@endforeach</select>
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Filter') }}</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Warehouse') }}</th><th>{{ __('Type') }}</th><th>{{ __('Region') }}</th><th>{{ __('TFRA no.') }}</th><th class="num">{{ __('Stock (t)') }}</th><th>{{ __('Utilisation') }}</th><th>{{ __('GPS') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($warehouses as $w)
                @php $t = (float) ($stock[$w->id]->tonnes ?? 0); $pct = $w->capacity_tonnes ? (int) round($t / $w->capacity_tonnes * 100) : 0; @endphp
                <tr>
                    <td><a href="{{ route('warehouses.show', $w) }}" class="link">{{ $w->name }}</a><p class="text-xs text-slate-500">{{ $w->code }} @unless($w->is_active)<x-badge color="red">{{ __('Inactive') }}</x-badge>@endunless</p></td>
                    <td><span class="inline-flex items-center gap-1.5 text-xs"><span class="size-2 rounded-full" style="background: {{ $w->type->color() }}"></span>{{ $w->type->label() }}</span></td>
                    <td>{{ $w->region->name }}</td>
                    <td class="text-xs">{{ $w->tfra_number ?: '—' }}</td>
                    <td class="num font-medium">{{ number_format($t, 1) }}</td>
                    <td class="min-w-36"><div class="flex items-center gap-2"><x-progress :percent="$pct" :color="$pct > 90 ? 'bg-red-500' : 'bg-brand-500'" /><span class="w-16 text-right text-xs text-slate-500 tabular-nums">{{ $pct }}% / {{ number_format($w->capacity_tonnes) }}t</span></div></td>
                    <td>@if ($w->lat)<x-badge color="green">{{ __('Yes') }}</x-badge>@else<x-badge color="red">{{ __('Missing') }}</x-badge>@endif</td>
                    <td class="text-right">@if (auth()->user()->hasAbility('warehouses.manage'))<a href="{{ route('warehouses.edit', $w) }}" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /></a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="warehouse" :title="__('No warehouses found')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $warehouses->links() }}</div>
</x-layouts.app>
