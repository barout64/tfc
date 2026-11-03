<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\DailyReport;
use App\Models\Product;
use App\Models\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Daily Report (Taarifa ya Mauzo ya Kila Siku): each station reports what it sold each day, product by
 * product. A station's people only see and submit for their own station; an administrator sees every
 * station and may submit or correct for any of them.
 */
class DailyReportController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();
        $stationId = $me->is_admin ? $request->query('station_id') : $me->station_id;
        $product = $request->query('product') ?: null;
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: now()->toDateString();

        $reports = DailyReport::query()->with(['station', 'submitter', 'lines'])
            ->when($stationId, fn ($q) => $q->where('station_id', $stationId))
            ->when($product, fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('product', $product)))
            ->whereDate('report_date', '>=', $from)->whereDate('report_date', '<=', $to)
            ->orderByDesc('report_date')->orderBy('station_id')
            ->paginate(30)->withQueryString();

        $stations = Station::query()->where('is_active', true)->orderBy('name')->get();
        $today = now()->toDateString();
        $reportedToday = DailyReport::query()->whereDate('report_date', $today)->pluck('station_id');

        return view('reports.index', [
            'reports' => $reports,
            'stations' => $stations,
            'stationId' => $stationId,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'product' => $product,
            'from' => $from,
            'to' => $to,
            'missingToday' => $me->is_admin ? $stations->reject(fn ($s) => $reportedToday->contains($s->id)) : collect(),
            'myStationReportedToday' => $me->station_id ? $reportedToday->contains($me->station_id) : null,
            'totalToday' => DailyReport::query()->whereDate('report_date', $today)
                ->when(! $me->is_admin, fn ($q) => $q->where('station_id', $me->station_id))
                ->with('lines')->get()->sum(fn ($r) => $r->totalAmount()),
        ]);
    }

    public function create(Request $request): View
    {
        $me = $request->user();
        $date = $request->query('date') ?: now()->toDateString();
        $stationId = $me->is_admin ? $request->query('station_id') : $me->station_id;

        $existing = $stationId
            ? DailyReport::query()->where('station_id', $stationId)->whereDate('report_date', $date)->with('lines')->first()
            : null;

        return view('reports.create', [
            'stations' => Station::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'station' => $me->is_admin ? null : $me->station,
            'date' => $date,
            'existing' => $existing,
            'existingLines' => $existing ? $existing->lines->map(fn ($l) => [
                'product' => $l->product,
                'unit' => $l->unit,
                'quantity' => (float) $l->quantity,
                'unit_price' => $l->unit_price !== null ? (float) $l->unit_price : null,
                'amount' => (float) $l->amount,
            ])->values() : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();

        $data = $request->validate([
            'station_id' => $me->is_admin
                ? ['required', 'integer', Rule::exists('stations', 'id')]
                : ['prohibited'],
            'report_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subDays(14)->toDateString()],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product' => ['required', 'string', 'max:60'],
            'lines.*.unit' => ['required', 'string', 'max:20'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $stationId = $me->is_admin ? $data['station_id'] : $me->station_id;
        abort_unless($stationId, 403, 'You are not tied to a station. Ask the TFC administrator.');

        $report = DB::transaction(function () use ($data, $stationId, $me) {
            $report = DailyReport::query()->where('station_id', $stationId)
                ->whereDate('report_date', $data['report_date'])->first()
                ?? new DailyReport(['station_id' => $stationId, 'report_date' => $data['report_date']]);

            $report->fill(['submitted_by' => $me->id, 'notes' => $data['notes'] ?? null])->save();

            $report->lines()->delete();
            foreach ($data['lines'] as $i => $line) {
                $report->lines()->create([
                    'product' => $line['product'],
                    'unit' => $line['unit'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'] ?? null,
                    'amount' => $line['amount'],
                    'sort' => $i,
                ]);
            }

            return $report;
        });

        Activity::record('submitted_daily_report', null, $report->station->name.' · '.$report->report_date->toDateString());

        return redirect()->route('reports.show', $report)->with('status', 'Report saved.');
    }

    public function show(Request $request, DailyReport $dailyReport): View
    {
        $me = $request->user();
        abort_unless($me->is_admin || $dailyReport->station_id === $me->station_id, 403);

        return view('reports.show', ['report' => $dailyReport->load(['station', 'submitter', 'lines'])]);
    }
}
