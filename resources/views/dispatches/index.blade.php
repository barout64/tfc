<x-layouts.app :title="__('Dispatches')">
    <x-page-header :title="__('Dispatches & tracking')" :subtitle="__('Every truck movement: approval, loading, GPS tracking and proof of delivery.')">
        <x-slot:actions>
            <a href="{{ route('map') }}" class="btn-secondary"><x-icon name="map" class="size-4" />{{ __('Live map') }}</a>
            @if (auth()->user()->hasAbility('logistics.manage'))
                <a href="{{ route('dispatches.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" />{{ __('New dispatch') }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('dispatches.index') }}" @class(['btn btn-sm', 'bg-slate-900 text-white' => ! request('status'), 'btn-secondary' => request('status')])>{{ __('All') }} <span class="opacity-60">{{ $counts->sum() }}</span></a>
        @foreach ($statuses as $v => $l)
            <a href="{{ route('dispatches.index', ['status' => $v]) }}" @class(['btn btn-sm', 'bg-slate-900 text-white' => request('status') === $v, 'btn-secondary' => request('status') !== $v])>{{ $l }} <span class="opacity-60">{{ $counts[$v] ?? 0 }}</span></a>
        @endforeach
    </div>

    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-4">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Dispatch number…') }}" class="input">
        <select name="transporter" class="input"><option value="">{{ __('All transporters') }}</option>@foreach ($transporters as $t)<option value="{{ $t->id }}" @selected(request('transporter') == $t->id)>{{ $t->name }}</option>@endforeach</select>
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Filter') }}</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Dispatch') }}</th><th>{{ __('Route') }}</th><th>{{ __('Transporter / vehicle') }}</th><th class="num">{{ __('Load') }}</th><th>{{ __('Departed') }}</th><th>ETA / {{ __('Delivered') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($dispatches as $d)
                <tr>
                    <td class="whitespace-nowrap"><a href="{{ route('dispatches.show', $d) }}" class="link">{{ $d->number }}</a><p class="text-xs text-slate-500">{{ $d->planned_date?->format('d M Y') }}</p></td>
                    <td class="max-w-64"><p class="truncate">{{ $d->fromWarehouse->name }}</p><p class="truncate text-xs text-slate-500">→ {{ $d->toWarehouse->name }} · {{ $d->distance_km }} km</p></td>
                    <td>{{ $d->transporter?->name ?? '—' }}<p class="text-xs text-slate-500">{{ $d->vehicle?->plate_number }}</p></td>
                    <td class="num">{{ number_format($d->totalTonnes(), 1) }} t<p class="text-xs text-slate-500">{{ $d->items->pluck('batch.product.code')->unique()->implode(', ') }}</p></td>
                    <td class="text-xs">{{ $d->dispatched_at?->format('d M H:i') ?? '—' }}</td>
                    <td class="text-xs">
                        @if ($d->delivered_at){{ $d->delivered_at->format('d M H:i') }}@elseif ($d->expected_arrival_at)<span @class(['text-red-600 font-medium' => $d->isLate()])>{{ $d->expected_arrival_at->format('d M H:i') }}</span>@else — @endif
                        @if ($d->lostBags())<p class="text-red-600">{{ $d->lostBags() }} {{ __('bags lost') }}</p>@endif
                    </td>
                    <td><x-badge :color="$d->isLate() && $d->status->value === 'in_transit' ? 'red' : $d->status->color()">{{ $d->isLate() && $d->status->value === 'in_transit' ? __('Overdue') : $d->status->label() }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="truck" :title="__('No dispatches')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $dispatches->links() }}</div>
</x-layouts.app>
