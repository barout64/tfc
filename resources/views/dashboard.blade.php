<x-layouts.app :title="__('Dashboard')">
    <x-page-header :title="__('Fertilizer distribution dashboard')" :subtitle="__('Season :season · updated :time', ['season' => $season->name, 'time' => now()->format('d M Y H:i')])">
        <x-slot:actions>
            @if (auth()->user()->hasAbility('logistics.manage'))
                <a href="{{ route('dispatches.create') }}" class="btn-secondary"><x-icon name="truck" class="size-4" />{{ __('New dispatch') }}</a>
            @endif
            @if (auth()->user()->hasAbility('sales.manage'))
                <a href="{{ route('sales.create') }}" class="btn-primary"><x-icon name="cart" class="size-4" />{{ __('Point of sale') }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Latest official stock position report --}}
    @if ($stockReport)
        <a href="{{ route('stock-reports.show', $stockReport) }}" class="card mb-6 block p-5 transition hover:border-brand-300 hover:shadow">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title flex items-center gap-2"><x-icon name="document" class="size-4 text-brand-600" />{{ __('Stock position as at :date', ['date' => $stockReport->report_date->format('d/m/Y')]) }}</h2>
                <span class="link text-xs">{{ __('Open report') }} &rarr;</span>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div><p class="text-xs text-slate-500">{{ __('Distributed') }}</p><p class="text-lg font-semibold tabular-nums">{{ number_format($stockReportTotals['distributed_tonnes'], 1) }} t</p></div>
                <div><p class="text-xs text-slate-500">{{ __('Sold at centres') }}</p><p class="text-lg font-semibold tabular-nums">{{ number_format($stockReportTotals['sold_tonnes'], 1) }} t</p></div>
                <div><p class="text-xs text-slate-500">{{ __('Actual DAP balance') }}</p><p class="text-lg font-semibold tabular-nums">{{ number_format($stockReportTotals['actual_dap_balance_tonnes'], 1) }} t</p></div>
                <div><p class="text-xs text-slate-500">{{ __('Cash sale') }}</p><p class="text-lg font-semibold tabular-nums">TZS {{ number_format($stockReportTotals['cash_sale_tzs'] / 1e9, 2) }} bn</p></div>
                <div><p class="text-xs text-slate-500">{{ __('Subsidy') }}</p><p class="text-lg font-semibold tabular-nums">TZS {{ number_format($stockReportTotals['subsidy_tzs'] / 1e9, 2) }} bn</p></div>
            </div>
        </a>
    @endif

    {{-- KPI row --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Stock available')" :value="number_format($stockTonnes, 1).' t'" :sub="number_format($stock->sum('bags')).' '.__('bags')" icon="warehouse" :href="route('stock.index')" />
        <x-stat :label="__('Sold this season')" :value="number_format($sales['tonnes'], 1).' t'" icon="cart" tone="amber" :href="route('sales.index')">
            <x-progress :percent="$targetPercent" class="mt-2 w-40" />
            <p class="mt-1 text-xs whitespace-nowrap text-slate-500">{{ $targetPercent }}% {{ __('of') }} {{ number_format($target) }} t {{ __('target') }}</p>
        </x-stat>
        <x-stat :label="__('Farmers served')" :value="number_format($sales['farmers'])" :sub="number_format($counts['farmers']).' '.__('registered')" icon="users" tone="blue" :href="route('farmers.index')" />
        <x-stat :label="__('Trucks on the road')" :value="$logistics['in_transit']" :sub="$logistics['late'] ? $logistics['late'].' '.__('overdue') : __('All on schedule')" icon="truck" :tone="$logistics['late'] ? 'red' : 'violet'" :href="route('map')" />
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-4"><p class="text-xs text-slate-500">{{ __('Sales value') }}</p><p class="mt-1 text-lg font-semibold tabular-nums">TZS {{ number_format($sales['amount']) }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">{{ __('Government subsidy') }}</p><p class="mt-1 text-lg font-semibold tabular-nums">TZS {{ number_format($sales['subsidy']) }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">{{ __('On-time deliveries') }}</p><p class="mt-1 text-lg font-semibold tabular-nums">{{ $logistics['on_time_percent'] }}% <span class="text-xs font-normal text-slate-500">· {{ $logistics['delivered'] }} {{ __('trips') }}</span></p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">{{ __('Transit losses') }}</p><p class="mt-1 text-lg font-semibold tabular-nums {{ $logistics['lost_bags'] ? 'text-red-600' : '' }}">{{ number_format($logistics['lost_bags']) }} {{ __('bags') }}</p></div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        {{-- Stock by product --}}
        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">{{ __('Stock by product (tonnes)') }}</h2><a href="{{ route('stock.index') }}" class="link text-xs">{{ __('Details') }}</a></div>
            <div class="p-5"><div class="h-64"><canvas id="stockChart"></canvas></div></div>
        </div>

        {{-- Alerts --}}
        <div class="card">
            <div class="card-header"><h2 class="card-title flex items-center gap-2"><x-icon name="bell" class="size-4 text-harvest-500" />{{ __('Needs attention') }}</h2><x-badge :color="count($alerts) ? 'red' : 'green'">{{ count($alerts) }}</x-badge></div>
            <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                @forelse ($alerts as $alert)
                    <a href="{{ $alert['url'] }}" class="flex gap-3 px-5 py-3 hover:bg-slate-50">
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-red-500' => $alert['level'] === 'danger', 'bg-amber-500' => $alert['level'] === 'warning', 'bg-sky-500' => $alert['level'] === 'info'])></span>
                        <span class="min-w-0"><span class="block text-sm font-medium text-slate-800">{{ $alert['title'] }}</span><span class="block truncate text-xs text-slate-500">{{ $alert['detail'] }}</span></span>
                    </a>
                @empty
                    <x-empty icon="check" :title="__('All clear')" :text="__('No stock, transport or farmer issues right now.')" />
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">{{ __('Monthly sales (bags)') }}</h2></div>
            <div class="p-5"><div class="h-64"><canvas id="salesChart"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Regional target progress') }}</h2><a href="{{ route('reports.show', 'target-vs-actual') }}" class="link text-xs">{{ __('All regions') }}</a></div>
            <div class="space-y-3.5 p-5">
                @forelse ($targetProgress as $row)
                    <div>
                        <div class="mb-1 flex justify-between text-xs"><span class="font-medium text-slate-700">{{ $row->name }}</span><span class="text-slate-500 tabular-nums">{{ number_format($row->sold, 1) }} / {{ number_format($row->target) }} t</span></div>
                        <x-progress :percent="$row->percent" />
                    </div>
                @empty
                    <x-empty icon="target" :title="__('No targets set')" />
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Trucks in transit') }}</h2><a href="{{ route('map') }}" class="link text-xs">{{ __('Open map') }}</a></div>
            <div class="divide-y divide-slate-100">
                @forelse ($activeTrips as $trip)
                    <a href="{{ route('dispatches.show', $trip) }}" class="flex items-center gap-4 px-5 py-3 hover:bg-slate-50">
                        <span @class(['flex size-9 items-center justify-center rounded-lg', 'bg-red-50 text-red-600' => $trip->isLate(), 'bg-sky-50 text-sky-600' => ! $trip->isLate()])><x-icon name="truck" class="size-5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $trip->fromWarehouse->name }} → {{ $trip->toWarehouse->name }}</p>
                            <p class="text-xs text-slate-500">{{ $trip->number }} · {{ $trip->vehicle?->plate_number }} · {{ number_format($trip->totalTonnes(), 1) }} t</p>
                        </div>
                        <div class="text-right text-xs">
                            <p class="text-slate-500">ETA</p>
                            <p @class(['font-medium', 'text-red-600' => $trip->isLate(), 'text-slate-800' => ! $trip->isLate()])>{{ $trip->expected_arrival_at?->format('d M H:i') }}</p>
                        </div>
                    </a>
                @empty
                    <x-empty icon="truck" :title="__('No trucks on the road')" />
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Latest sales') }}</h2><a href="{{ route('sales.index') }}" class="link text-xs">{{ __('All sales') }}</a></div>
            <div class="divide-y divide-slate-100">
                @forelse ($recentSales as $sale)
                    <a href="{{ route('sales.show', $sale) }}" class="flex items-center gap-4 px-5 py-3 hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $sale->customerName() }}</p>
                            <p class="text-xs text-slate-500">{{ $sale->number }} · {{ $sale->warehouse->name }} · {{ $sale->sold_at->diffForHumans() }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium tabular-nums">{{ number_format($sale->total_bags) }} {{ __('bags') }}</p>
                            <p class="text-xs text-slate-500 tabular-nums">TZS {{ number_format($sale->total_amount) }}</p>
                        </div>
                    </a>
                @empty
                    <x-empty icon="cart" :title="__('No sales yet')" />
                @endforelse
            </div>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const stock = {{ \Illuminate\Support\Js::from($stock) }};
            new Chart(document.getElementById('stockChart'), {
                type: 'bar',
                data: {
                    labels: stock.map(s => s.code),
                    datasets: [{ data: stock.map(s => Number(s.tonnes).toFixed(1)), backgroundColor: stock.map(s => s.color), borderRadius: 6, maxBarThickness: 56 }],
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } } },
            });
            const months = {{ \Illuminate\Support\Js::from($salesByMonth) }};
            new Chart(document.getElementById('salesChart'), {
                type: 'line',
                data: {
                    labels: months.map(m => m.month),
                    datasets: [{ label: 'Bags', data: months.map(m => m.bags), borderColor: '#187b47', backgroundColor: 'rgba(38,153,90,.12)', fill: true, tension: .35, pointRadius: 3 }],
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } } },
            });
        </script>
    @endpush
</x-layouts.app>
