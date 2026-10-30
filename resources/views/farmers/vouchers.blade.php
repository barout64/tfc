<x-layouts.app :title="__('E-vouchers')">
    <x-page-header :title="__('E-vouchers')" :subtitle="__('SMS vouchers issued to farmers and redeemed at sale points or agro-dealers.')" />
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-3">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Voucher code…') }}" class="input">
        <select name="status" class="input"><option value="">{{ __('All statuses') }}</option>@foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Filter') }}</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Farmer') }}</th><th>{{ __('Product') }}</th><th class="num">{{ __('Bags') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Redeemed at') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($vouchers as $v)
                <tr>
                    <td class="font-mono font-semibold">{{ $v->code }}</td>
                    <td><a href="{{ route('farmers.show', $v->farmer) }}" class="link">{{ $v->farmer->fullName() }}</a></td>
                    <td>{{ $v->product->code }}</td>
                    <td class="num">{{ $v->bags }}</td>
                    <td class="text-xs">{{ $v->created_at->format('d M Y') }}</td>
                    <td class="text-xs">{{ $v->expires_at?->format('d M Y') }}</td>
                    <td class="text-xs">{{ $v->redeemedAt?->name ?? '—' }}</td>
                    <td><x-badge :color="$v->status->color()">{{ $v->status->label() }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="ticket" :title="__('No vouchers')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $vouchers->links() }}</div>
</x-layouts.app>
