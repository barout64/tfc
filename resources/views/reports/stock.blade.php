@extends('layouts.app', ['title' => 'Monthly stock'])

@section('content')
    @php
        $me = auth()->user();
        $qty = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $input = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $monthName = fn ($m) => \Illuminate\Support\Carbon::parse($m.'-01')->format('F Y');
    @endphp
    <a href="{{ route('reports.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Daily Report</a>
    <h1 class="mt-2 text-2xl font-bold text-slate-900">Monthly stock</h1>
    <p class="mt-1 text-sm text-slate-500">Opening stock at the beginning of the month and closing stock at the end, for each station. What was sold comes from the daily reports.</p>

    <form method="GET" class="my-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <div>
            <label class="label" for="month">Month</label>
            <select class="field" id="month" name="month">
                @foreach ($months as $m)<option value="{{ $m }}" @selected($m === $month)>{{ $monthName($m) }}</option>@endforeach
            </select>
        </div>
        @if ($me->is_admin)
            <div>
                <label class="label" for="station_id">Station</label>
                <select class="field" id="station_id" name="station_id">
                    <option value="">All stations</option>
                    @foreach ($stations as $st)<option value="{{ $st->id }}" @selected($station?->id === $st->id)>{{ $st->name }}</option>@endforeach
                </select>
            </div>
        @endif
        <button class="btn-light">Show</button>
    </form>

    @if ($station)
        <form method="POST" action="{{ route('reports.stock.store') }}" class="card p-4">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">
            @if ($me->is_admin)<input type="hidden" name="station_id" value="{{ $station->id }}">@endif
            <p class="mb-3 font-semibold text-slate-900">{{ $station->name }} · {{ $monthName($month) }}</p>
            @error('month')<p class="mb-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th class="pb-2 pr-2">Product</th>
                            <th class="pb-2 pr-2">Unit</th>
                            <th class="pb-2 pr-2">Opening stock</th>
                            <th class="pb-2 pr-2 text-right">Sold to agro dealers</th>
                            <th class="pb-2 pr-2 text-right">Sold to farmers</th>
                            <th class="pb-2 pr-2">Closing stock</th>
                            <th class="pb-2 text-right">Difference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $i => $row)
                            <tr>
                                <td class="py-1 pr-2 font-medium text-slate-900">{{ $row['product'] }}<input type="hidden" name="rows[{{ $i }}][product]" value="{{ $row['product'] }}">
                                    @if ($row['not_split'] > 0)<span class="block text-xs font-normal text-slate-500">{{ $qty($row['not_split']) }} sold in older reports, buyer not recorded</span>@endif</td>
                                <td class="py-1 pr-2"><select class="field" name="rows[{{ $i }}][unit]" aria-label="Unit for {{ $row['product'] }}">
                                    @foreach (collect($units)->push($row['unit'])->unique() as $u)<option @selected($u === $row['unit'])>{{ $u }}</option>@endforeach
                                </select></td>
                                <td class="py-1 pr-2"><input class="field" type="number" step="0.01" min="0" name="rows[{{ $i }}][opening]" value="{{ old("rows.$i.opening", $input($row['opening'])) }}" aria-label="Opening stock of {{ $row['product'] }}">
                                    @if ($row['carried'])<span class="block text-xs text-slate-500">From last month's closing</span>@endif</td>
                                <td class="py-1 pr-2 text-right">{{ $qty($row['agro_dealer']) }}</td>
                                <td class="py-1 pr-2 text-right">{{ $qty($row['farmer']) }}</td>
                                <td class="py-1 pr-2"><input class="field" type="number" step="0.01" min="0" name="rows[{{ $i }}][closing]" value="{{ old("rows.$i.closing", $input($row['closing'])) }}" aria-label="Closing stock of {{ $row['product'] }}"></td>
                                <td class="py-1 text-right font-medium {{ ($row['difference'] ?? 0) != 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $row['difference'] === null ? '—' : ($row['difference'] > 0 ? '+' : '').$qty($row['difference']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-500">Difference = closing − (opening − sold). A plus is usually stock received during the month; a minus is stock to explain.</p>
            <div class="mt-4 flex justify-end"><button class="btn-primary">Save stock</button></div>
        </form>
    @else
        @forelse ($everyStation as $entry)
            <div class="card mb-4 overflow-x-auto">
                <p class="px-4 pt-4 font-semibold text-slate-900"><a class="hover:underline" href="{{ route('reports.stock', ['month' => $month, 'station_id' => $entry['station']->id]) }}">{{ $entry['station']->name }}</a></p>
                <table class="mt-2 w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-2">Product</th>
                            <th class="px-4 py-2 text-right">Opening</th>
                            <th class="px-4 py-2 text-right">Agro dealers</th>
                            <th class="px-4 py-2 text-right">Farmers</th>
                            <th class="px-4 py-2 text-right">Closing</th>
                            <th class="px-4 py-2 text-right">Difference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($entry['rows'] as $row)
                            <tr>
                                <td class="px-4 py-2 font-medium text-slate-900">{{ $row['product'] }} <span class="font-normal text-slate-500">({{ $row['unit'] }})</span></td>
                                <td class="px-4 py-2 text-right">{{ $qty($row['opening']) }}</td>
                                <td class="px-4 py-2 text-right">{{ $qty($row['agro_dealer']) }}</td>
                                <td class="px-4 py-2 text-right">{{ $qty($row['farmer']) }}</td>
                                <td class="px-4 py-2 text-right">{{ $qty($row['closing']) }}</td>
                                <td class="px-4 py-2 text-right font-medium {{ ($row['difference'] ?? 0) != 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $row['difference'] === null ? '—' : ($row['difference'] > 0 ? '+' : '').$qty($row['difference']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="card p-10 text-center text-slate-600">No stock or sales for {{ $monthName($month) }} yet. Choose a station to enter its stock.</div>
        @endforelse
    @endif
@endsection
