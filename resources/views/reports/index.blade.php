@extends('layouts.app', ['title' => 'Daily Report'])

@section('content')
    @php($me = auth()->user())
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold tracking-wide text-brand-600 uppercase">Daily Report</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Taarifa ya Mauzo ya Kila Siku</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $me->is_admin ? 'What every station sold, day by day.' : 'What your station sold, day by day.' }}</p>
        </div>
        <a href="{{ route('reports.create') }}" class="btn-primary">+ Submit today's report</a>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-3 {{ $me->is_admin ? 'md:grid-cols-3' : '' }}">
        <div class="card p-4">
            <p class="text-xs text-slate-500">Total today (TZS)</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalToday, 0) }}</p>
        </div>
        @if ($me->is_admin)
            <div class="card p-4">
                <p class="text-xs text-slate-500">Stations</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stations->count() }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-slate-500">Have not reported today</p>
                <p class="mt-1 text-2xl font-bold {{ $missingToday->count() ? 'text-red-600' : 'text-slate-900' }}">{{ $missingToday->count() }}</p>
            </div>
        @else
            <div class="card p-4">
                <p class="text-xs text-slate-500">Today's report</p>
                <p class="mt-1 text-2xl font-bold {{ $myStationReportedToday ? 'text-slate-900' : 'text-red-600' }}">{{ $myStationReportedToday ? 'Submitted' : 'Not yet' }}</p>
            </div>
        @endif
    </div>

    @if ($me->is_admin && $missingToday->isNotEmpty())
        <p class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">Have not reported today: {{ $missingToday->pluck('name')->implode(', ') }}</p>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 ring-1 ring-slate-200">
        @if ($me->is_admin)
            <div>
                <label class="label" for="station_id">Station</label>
                <select class="field" id="station_id" name="station_id">
                    <option value="">All stations</option>
                    @foreach ($stations as $st)
                        <option value="{{ $st->id }}" @selected((string) $stationId === (string) $st->id)>{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label class="label" for="product">Product</label>
            <select class="field" id="product" name="product">
                <option value="">All products</option>
                @foreach ($products as $p)
                    <option value="{{ $p->name }}" @selected($product === $p->name)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="from">From</label>
            <input class="field" id="from" name="from" type="date" value="{{ $from }}">
        </div>
        <div>
            <label class="label" for="to">To</label>
            <input class="field" id="to" name="to" type="date" value="{{ $to }}">
        </div>
        <button class="btn-light">Filter</button>
    </form>

    <div class="card overflow-hidden">
        @if ($reports->isEmpty())
            <div class="p-10 text-center">
                <p class="font-medium text-slate-700">{{ $product ? 'No reports sold '.$product.' in this range.' : 'No reports in this range.' }}</p>
                <a href="{{ route('reports.create') }}" class="btn-primary mt-4">Submit a report</a>
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($reports as $report)
                    <li>
                        <a href="{{ route('reports.show', $report) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-4 hover:bg-slate-50">
                            <span class="w-28 text-sm font-semibold text-slate-700">{{ $report->report_date->format('d M Y') }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-slate-900">{{ $report->station->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $report->lines->count() }} product(s) · submitted by {{ $report->submitter?->name ?? '—' }}</span>
                            </span>
                            <span class="font-semibold text-slate-900">{{ number_format($report->totalAmount(), 0) }} TZS</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <div class="mt-4">{{ $reports->links() }}</div>
@endsection
