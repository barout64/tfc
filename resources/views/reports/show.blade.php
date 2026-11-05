@extends('layouts.app', ['title' => $report->station->name.' · '.$report->report_date->format('d M Y')])

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('reports.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Daily Report</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $report->station->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $report->report_date->format('l, d F Y') }} · submitted by {{ $report->submitter?->name ?? '—' }} · {{ $report->updated_at->diffForHumans() }}</p>
            </div>
            <a href="{{ route('reports.create', ['date' => $report->report_date->toDateString(), 'station_id' => $report->station_id]) }}" class="btn-light">Edit this report</a>
        </div>

        @if ($report->notes)
            <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ $report->notes }}</p>
        @endif

        <div class="card mt-5 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Quantity</th>
                        <th class="px-4 py-3">Unit price</th>
                        <th class="px-4 py-3 text-right">Amount (TZS)</th>
                    </tr>
                </thead>
                @foreach ($report->lines->groupBy(fn ($l) => $l->buyer ?? '') as $buyer => $lines)
                    <tbody class="divide-y divide-slate-100">
                        <tr class="bg-slate-50/60"><td class="px-4 py-2 font-semibold text-slate-800" colspan="3">{{ \App\Models\DailyReportLine::BUYERS[$buyer] ?? 'Buyer not recorded' }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-slate-800">{{ number_format($lines->sum('amount'), 0) }}</td></tr>
                        @foreach ($lines as $line)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $line->product }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }} {{ $line->unit }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $line->unit_price !== null ? number_format($line->unit_price, 0) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium text-slate-900">{{ number_format($line->amount, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
                <tfoot>
                    <tr class="bg-slate-50 font-semibold text-slate-900">
                        <td class="px-4 py-3" colspan="3">Total</td>
                        <td class="px-4 py-3 text-right">{{ number_format($report->totalAmount(), 0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
