<x-layouts.app :title="$report->title">
    <x-page-header :title="$report->title" :subtitle="__('Imported :when by :who from :file', ['when' => $report->created_at->format('d M Y H:i'), 'who' => $report->creator?->name ?? __('System'), 'file' => $report->source_file ?? '—'])">
        <x-slot:actions>
            <a href="{{ route('stock-reports.index') }}" class="btn-secondary">{{ __('All reports') }}</a>
            <button type="button" onclick="window.print()" class="btn-secondary"><x-icon name="printer" class="size-4" />{{ __('Print') }}</button>
            @if (auth()->user()->hasAbility('approve') || auth()->user()->hasAbility('warehouses.manage'))
                <form method="POST" action="{{ route('stock-reports.apply', $report) }}" onsubmit="return confirm(@js(__('Set every centre\'s DAP, old DAP and UREA stock to the balances in this report?')))">
                    @csrf
                    <button class="btn-primary"><x-icon name="warehouse" class="size-4" />{{ $report->applied_to_stock_at ? __('Re-apply to centre stock') : __('Update centre stock') }}</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($report->applied_to_stock_at)
        <p class="mb-4 text-sm text-emerald-700">✓ {{ __('Centre stock was matched to this report on :date.', ['date' => $report->applied_to_stock_at->format('d M Y H:i')]) }}</p>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Stock distributed')" :value="number_format($totals['distributed_tonnes'], 2).' t'" icon="truck" tone="blue" />
        <x-stat :label="__('Sold at TFC centres')" :value="number_format($totals['sold_tonnes'], 2).' t'" :sub="'TZS '.number_format($totals['cash_sale_tzs'])" icon="cart" tone="amber" />
        <x-stat :label="__('Balance at centres')" :value="number_format($totals['balance_tonnes'], 2).' t'" :sub="__('Value').' TZS '.number_format($totals['stock_value_tzs'])" icon="warehouse" />
        <x-stat :label="__('Actual DAP balance')" :value="number_format($totals['actual_dap_balance_tonnes'], 2).' t'" :sub="$totals['dap_bulk_sold_tonnes'] ? __('after :t t sold in bulk / export', ['t' => number_format($totals['dap_bulk_sold_tonnes'], 2)]) : null" icon="cube" tone="violet" />
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="card p-4"><p class="text-xs text-slate-500">{{ __('Government subsidy on stock sold') }}</p><p class="mt-1 text-lg font-semibold tabular-nums">TZS {{ number_format($totals['subsidy_tzs']) }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">{{ __('DAP old stock') }}</p><p class="mt-1 text-lg font-semibold tabular-nums">{{ number_format($totals['dap_old_stock_tonnes'], 2) }} t</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">{{ __('UREA in stock') }}</p><p class="mt-1 text-lg font-semibold tabular-nums">{{ number_format($totals['urea_tonnes'], 2) }} t</p></div>
    </div>

    <div class="card mt-6 overflow-x-auto">
        <div class="card-header"><h2 class="card-title">{{ __('Stock position by centre') }}</h2><span class="text-xs text-slate-500">{{ __('Tonnes (MT) and TZS') }}</span></div>
        <table class="table">
            <thead><tr>
                <th>{{ __('Region') }}</th><th>{{ __('Centre') }}</th><th class="num">{{ __('Distributed') }}</th><th class="num">{{ __('Sold') }}</th><th class="num">{{ __('Balance') }}</th>
                <th class="num">{{ __('DAP old stock') }}</th><th class="num">{{ __('UREA') }}</th><th class="num">{{ __('Cash sale') }}</th><th class="num">{{ __('Subsidy') }}</th><th class="num">{{ __('Stock value') }}</th>
            </tr></thead>
            <tbody>
            @foreach ($report->lines as $l)
                <tr>
                    <td>{{ $l->region_label }}</td>
                    <td>@if ($l->warehouse)<a href="{{ route('warehouses.show', $l->warehouse) }}" class="link">{{ $l->center_label }}</a>@else{{ $l->center_label }}@endif</td>
                    <td class="num">{{ number_format($l->distributed_tonnes, 2) }}</td>
                    <td class="num">{{ number_format($l->sold_tonnes, 2) }}</td>
                    <td class="num font-medium">{{ number_format($l->balance_tonnes, 2) }}</td>
                    <td class="num">{{ (float) $l->dap_old_stock_tonnes ? number_format($l->dap_old_stock_tonnes, 2) : '—' }}</td>
                    <td class="num">{{ (float) $l->urea_tonnes ? number_format($l->urea_tonnes, 2) : '—' }}</td>
                    <td class="num">{{ number_format($l->cash_sale_tzs) }}</td>
                    <td class="num">{{ number_format($l->subsidy_tzs) }}</td>
                    <td class="num">{{ number_format($l->stock_value_tzs) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="bg-slate-50 font-semibold"><tr>
                <td colspan="2">{{ __('TOTAL') }}</td>
                <td class="num">{{ number_format($totals['distributed_tonnes'], 2) }}</td><td class="num">{{ number_format($totals['sold_tonnes'], 2) }}</td><td class="num">{{ number_format($totals['balance_tonnes'], 2) }}</td>
                <td class="num">{{ number_format($totals['dap_old_stock_tonnes'], 2) }}</td><td class="num">{{ number_format($totals['urea_tonnes'], 2) }}</td>
                <td class="num">{{ number_format($totals['cash_sale_tzs']) }}</td><td class="num">{{ number_format($totals['subsidy_tzs']) }}</td><td class="num">{{ number_format($totals['stock_value_tzs']) }}</td>
            </tr></tfoot>
        </table>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card overflow-x-auto">
            <div class="card-header"><h2 class="card-title">{{ __('Bulk & export sales') }}</h2></div>
            <table class="table">
                <thead><tr><th>{{ __('Sale') }}</th><th>{{ __('Product') }}</th><th class="num">{{ __('Tonnes') }}</th><th class="num">{{ __('USD / t') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                @forelse ($report->bulkSales as $s)
                    <tr>
                        <td>{{ $s->description }}@if ($s->buyer)<p class="text-xs text-slate-500">{{ __('Buyer') }}: {{ $s->buyer }}</p>@endif</td>
                        <td>{{ $s->product?->code ?? '—' }}</td>
                        <td class="num">{{ number_format($s->quantity_tonnes, 2) }}</td>
                        <td class="num">{{ $s->usd_per_tonne ? number_format($s->usd_per_tonne) : '—' }}</td>
                        <td class="num">@if ($s->amount_usd)USD {{ number_format($s->amount_usd) }}@endif @if ($s->amount_tzs)<br>TZS {{ number_format($s->amount_tzs) }}@endif @if (! $s->amount_usd && ! $s->amount_tzs)—@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-sm text-slate-500">{{ __('None in this report') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card overflow-x-auto">
            <div class="card-header"><h2 class="card-title">{{ __('Summary by product') }}</h2></div>
            <table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th class="num">{{ __('Sold (t)') }}</th><th class="num">{{ __('Cash sale') }}</th><th class="num">{{ __('Subsidy') }}</th><th class="num">{{ __('Balance (t)') }}</th><th class="num">{{ __('Stock value') }}</th></tr></thead>
                <tbody>
                @forelse ($report->productSummaries as $p)
                    <tr>
                        <td>{{ $p->label }}</td>
                        <td class="num">{{ number_format($p->sold_tonnes, 2) }}</td>
                        <td class="num">{{ (float) $p->cash_sale_amount ? $p->cash_sale_currency.' '.number_format($p->cash_sale_amount) : '—' }}</td>
                        <td class="num">{{ (float) $p->subsidy_tzs ? number_format($p->subsidy_tzs) : '—' }}</td>
                        <td class="num">{{ number_format($p->balance_tonnes, 2) }}</td>
                        <td class="num">{{ (float) $p->stock_value_amount ? $p->stock_value_currency.' '.number_format($p->stock_value_amount) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-sm text-slate-500">{{ __('No summary sheet in this report') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
