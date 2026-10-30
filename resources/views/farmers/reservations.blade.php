<x-layouts.app :title="__('Reservations')">
    <x-page-header :title="__('Reservations')" :subtitle="__('Bags farmers reserved online or by USSD before travelling to the sale point.')" />
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Farmer') }}</th><th>{{ __('Sale point') }}</th><th>{{ __('Product') }}</th><th class="num">{{ __('Bags') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($reservations as $r)
                <tr>
                    <td class="font-mono font-semibold">{{ $r->code }}</td>
                    <td><a href="{{ route('farmers.show', $r->farmer) }}" class="link">{{ $r->farmer->fullName() }}</a><p class="text-xs text-slate-500">{{ $r->farmer->phone }}</p></td>
                    <td>{{ $r->warehouse->name }}</td>
                    <td>{{ $r->product->code }}</td>
                    <td class="num">{{ $r->bags }}</td>
                    <td class="text-xs">{{ $r->expires_at->format('d M H:i') }}</td>
                    <td><x-badge :color="['pending' => 'amber', 'collected' => 'green', 'expired' => 'gray', 'cancelled' => 'red'][$r->status] ?? 'gray'">{{ __(ucfirst($r->status)) }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="clock" :title="__('No reservations')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $reservations->links() }}</div>
</x-layouts.app>
