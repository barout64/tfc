<x-layouts.app :title="__('Stock position reports')">
    <x-page-header :title="__('Stock position reports')" :subtitle="__('The weekly STOCK POSITION Excel: stock distributed, sold and remaining at every TFC centre, with cash sales, subsidy and exports.')" />

    @if (auth()->user()->hasAbility('approve') || auth()->user()->hasAbility('warehouses.manage'))
        <form method="POST" action="{{ route('stock-reports.store') }}" enctype="multipart/form-data" class="card mb-6 grid gap-4 p-5 lg:grid-cols-4 lg:items-end">
            @csrf
            <div class="lg:col-span-2">
                <label for="file" class="label">{{ __('Excel file (.xlsx)') }} <span class="text-red-500">*</span></label>
                <input id="file" type="file" name="file" accept=".xlsx" required class="input">
                <p class="mt-1 text-xs text-slate-500">{{ __('Same format as "STOCK POSITION AS AT …": sheets STOCK POSITION, SALES EXTRACT and SALES SUMMARY.') }}</p>
                @error('file')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>
            <x-field :label="__('Report date')" name="report_date" type="date" :hint="__('Leave empty to read it from the title.')" />
            <div class="space-y-3">
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="apply" value="1" checked class="mt-0.5 rounded border-slate-300 text-brand-600"> <span>{{ __('Also update each centre\'s stock to match') }}</span></label>
                <button class="btn-primary w-full"><x-icon name="document" class="size-4" />{{ __('Import report') }}</button>
            </div>
        </form>
    @endif

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('As at') }}</th><th>{{ __('Report') }}</th><th class="num">{{ __('Centres') }}</th><th class="num">{{ __('Distributed (t)') }}</th><th class="num">{{ __('Sold (t)') }}</th><th class="num">{{ __('Balance (t)') }}</th><th class="num">{{ __('Cash sale (TZS)') }}</th><th>{{ __('Centre stock') }}</th></tr></thead>
            <tbody>
            @forelse ($reports as $r)
                @php $t = $r->totals(); @endphp
                <tr>
                    <td class="whitespace-nowrap">{{ $r->report_date->format('d M Y') }}</td>
                    <td><a href="{{ route('stock-reports.show', $r) }}" class="link">{{ $r->title }}</a><p class="text-xs text-slate-500">{{ $r->source_file }} · {{ $r->creator?->name ?? __('System') }}</p></td>
                    <td class="num">{{ $r->lines->count() }}</td>
                    <td class="num">{{ number_format($t['distributed_tonnes'], 2) }}</td>
                    <td class="num">{{ number_format($t['sold_tonnes'], 2) }}</td>
                    <td class="num font-medium">{{ number_format($t['balance_tonnes'], 2) }}</td>
                    <td class="num">{{ number_format($t['cash_sale_tzs']) }}</td>
                    <td>@if ($r->applied_to_stock_at)<x-badge color="green">{{ __('Updated') }}</x-badge>@else<x-badge>{{ __('Not applied') }}</x-badge>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="document" :title="__('No stock position reports yet')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $reports->links() }}</div>
</x-layouts.app>
