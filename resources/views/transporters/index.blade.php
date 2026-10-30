<x-layouts.app :title="__('Transporters')">
    <x-page-header :title="__('Transporters')" :subtitle="__('Contracted transport companies, their fleet and performance this season.')">
        <x-slot:actions>
            @if (auth()->user()->hasAbility('logistics.manage'))
                <a href="{{ route('transporters.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" />{{ __('Add transporter') }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Transporter') }}</th><th class="num">{{ __('Fleet') }}</th><th class="num">{{ __('Rate / t·km') }}</th><th class="num">{{ __('Trips') }}</th><th class="num">{{ __('Tonnes') }}</th><th>{{ __('On time') }}</th><th class="num">{{ __('Loss rate') }}</th><th>{{ __('Contract') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($transporters as $t)
                @php $p = $performance[$t->id] ?? null; @endphp
                <tr>
                    <td><a href="{{ route('transporters.show', $t) }}" class="link">{{ $t->name }}</a><p class="text-xs text-slate-500">{{ $t->phone }}</p></td>
                    <td class="num">{{ $t->vehicles_count }} {{ __('vehicles') }}<p class="text-xs text-slate-500">{{ $t->drivers_count }} {{ __('drivers') }}</p></td>
                    <td class="num">TZS {{ number_format($t->rate_per_tonne_km) }}</td>
                    <td class="num">{{ $p->trips ?? 0 }}</td>
                    <td class="num">{{ number_format($p->tonnes ?? 0, 1) }}</td>
                    <td class="min-w-32">@if ($p)<div class="flex items-center gap-2"><x-progress :percent="$p->on_time_percent" /><span class="text-xs tabular-nums">{{ $p->on_time_percent }}%</span></div>@else — @endif</td>
                    <td @class(['num', 'text-red-600 font-medium' => ($p->loss_rate ?? 0) > 0.5])>{{ $p ? $p->loss_rate.'%' : '—' }}</td>
                    <td class="text-xs">@if ($t->contract_expires_on)<span @class(['text-red-600' => $t->contract_expires_on->isPast()])>{{ $t->contract_expires_on->format('d M Y') }}</span>@else — @endif</td>
                    <td><x-badge :color="$t->status === 'active' ? 'green' : 'gray'">{{ ucfirst($t->status) }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty icon="truck" :title="__('No transporters')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transporters->links() }}</div>
</x-layouts.app>
