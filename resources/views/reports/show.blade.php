<x-layouts.app :title="__($report['title'])">
    <x-page-header :title="__($report['title'])" :subtitle="__($report['description'])">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn-ghost no-print">{{ __('All reports') }}</a>
            <button onclick="window.print()" class="btn-secondary no-print"><x-icon name="printer" class="size-4" />{{ __('Print / PDF') }}</button>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn-primary no-print"><x-icon name="download" class="size-4" />{{ __('Excel (CSV)') }}</a>
        </x-slot:actions>
    </x-page-header>
    @if ($report['dated'])
        <form class="card no-print mb-4 flex flex-wrap items-end gap-3 p-4">
            <div><label class="label">{{ __('From') }}</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input"></div>
            <div><label class="label">{{ __('To') }}</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input"></div>
            <button class="btn-secondary">{{ __('Apply') }}</button>
        </form>
    @endif
    @php $numeric = collect(array_values((array) ($rows->first() ?? [])))->map(fn ($cell) => is_numeric($cell)); @endphp
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr>@foreach ($columns as $i => $c)<th @class(['num' => $numeric[$i] ?? false])>{{ __($c) }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>@foreach (array_values((array) $row) as $cell)<td @class(['num' => is_numeric($cell)])>{{ is_float($cell) ? number_format($cell, $cell == floor($cell) ? 0 : 2) : (is_int($cell) ? number_format($cell) : ($cell ?? '—')) }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}"><x-empty icon="document" :title="__('No data for this period')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-400">{{ __('Generated') }} {{ now()->format('d M Y H:i') }} {{ __('by') }} {{ auth()->user()->name }} · {{ count($rows) }} {{ __('rows') }}</p>
</x-layouts.app>
