<x-layouts.app :title="__('Stock ledger')">
    <x-page-header :title="__('Stock ledger')" :subtitle="__('Every bag in and out, with who did it and why. Entries cannot be edited or deleted.')" />
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-5">
        <select name="warehouse" class="input"><option value="">{{ __('All warehouses') }}</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected(request('warehouse') == $w->id)>{{ $w->name }}</option>@endforeach</select>
        <select name="type" class="input"><option value="">{{ __('All movement types') }}</option>@foreach ($types as $v => $l)<option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ request('from') }}" class="input">
        <input type="date" name="to" value="{{ request('to') }}" class="input">
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Filter') }}</button>
    </form>
    <div class="card overflow-x-auto">@include('stock._movements', ['movements' => $movements])</div>
    <div class="mt-4">{{ $movements->links() }}</div>
</x-layouts.app>
