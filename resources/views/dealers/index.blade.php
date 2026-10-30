<x-layouts.app :title="__('Agro-dealers')">
    <x-page-header :title="__('Agro-dealers')" :subtitle="__('Applications from the public portal, TFRA licence checks and approved dealers.')" />
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('dealers.index') }}" @class(['btn btn-sm', 'bg-slate-900 text-white' => ! request('status'), 'btn-secondary' => request('status')])>{{ __('All') }} <span class="opacity-60">{{ $counts->sum() }}</span></a>
        @foreach ($statuses as $v => $l)
            <a href="{{ route('dealers.index', ['status' => $v]) }}" @class(['btn btn-sm', 'bg-slate-900 text-white' => request('status') === $v, 'btn-secondary' => request('status') !== $v])>{{ $l }} <span class="opacity-60">{{ $counts[$v] ?? 0 }}</span></a>
        @endforeach
    </div>
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-4">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Business, dealer no. or TFRA licence') }}" class="input sm:col-span-2">
        <select name="region" class="input"><option value="">{{ __('All regions') }}</option>@foreach ($regions as $r)<option value="{{ $r->id }}" @selected(request('region') == $r->id)>{{ $r->name }}</option>@endforeach</select>
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Search') }}</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Dealer') }}</th><th>{{ __('Owner') }}</th><th>{{ __('Region') }}</th><th>{{ __('TFRA licence') }}</th><th>{{ __('Applied') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($dealers as $d)
                <tr>
                    <td><a href="{{ route('dealers.show', $d) }}" class="link">{{ $d->business_name }}</a><p class="text-xs text-slate-500">{{ $d->dealer_number }}</p></td>
                    <td>{{ $d->owner_name }}<p class="text-xs text-slate-500">{{ $d->phone }}</p></td>
                    <td>{{ $d->region->name }}</td>
                    <td class="text-xs">{{ $d->tfra_license_no }}<p @class(['text-red-600 font-medium' => $d->isLicenseExpired(), 'text-slate-500' => ! $d->isLicenseExpired()])>{{ __('exp.') }} {{ $d->license_expiry?->format('d M Y') }}</p></td>
                    <td class="text-xs">{{ $d->created_at->format('d M Y') }}</td>
                    <td><x-badge :color="$d->status->color()">{{ $d->status->label() }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="store" :title="__('No agro-dealers')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $dealers->links() }}</div>
</x-layouts.app>
