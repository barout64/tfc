<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\DailyReportLine;
use App\Models\Product;
use App\Models\Station;
use App\Models\StationStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Monthly stock: each station's opening and closing stock per product, beside what its daily reports say it sold to
 * agro dealers and farmers. A station's people see and enter their own; an administrator sees every station.
 */
class StationStockController extends Controller
{
    private const FIRST_MONTH = '2026-10';

    public const UNITS = ['Bags', 'Tonnes', 'Kg', 'Litres', 'Pieces'];

    public function index(Request $request): View
    {
        $me = $request->user();
        $month = (string) $request->query('month', '');
        $month = $this->monthAllowed($month) ? $month : now()->format('Y-m');
        $stations = Station::query()->where('is_active', true)->orderBy('name')->get();
        $station = $me->is_admin
            ? ($request->filled('station_id') ? Station::query()->findOrFail((int) $request->query('station_id')) : null)
            : $me->station;

        return view('reports.stock', [
            'month' => $month,
            'months' => $this->months(),
            'stations' => $stations,
            'station' => $station,
            'rows' => $station ? $this->rows($station, $month, true) : [],
            // Every station at once, for the administrator: only what has stock entered or sales reported
            'everyStation' => $station ? [] : $stations->map(fn ($s) => ['station' => $s, 'rows' => $this->rows($s, $month, false)])->filter(fn ($s) => $s['rows'] !== [])->values()->all(),
            'units' => self::UNITS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();
        $data = $request->validate([
            'station_id' => $me->is_admin ? ['required', 'integer', Rule::exists('stations', 'id')] : ['prohibited'],
            'month' => ['required', 'date_format:Y-m', function ($attribute, $value, $fail) {
                $this->monthAllowed((string) $value) || $fail('Choose a month from October 2026 up to this month.');
            }],
            'rows' => ['required', 'array'],
            'rows.*.product' => ['required', 'string', 'max:60'],
            'rows.*.unit' => ['required', 'string', 'max:20'],
            'rows.*.opening' => ['nullable', 'numeric', 'min:0'],
            'rows.*.closing' => ['nullable', 'numeric', 'min:0'],
        ]);
        $stationId = $me->is_admin ? (int) $data['station_id'] : $me->station_id;
        abort_unless($stationId, 403, 'You are not tied to a station. Ask the TFC administrator.');

        DB::transaction(function () use ($data, $stationId, $me) {
            StationStock::query()->where('station_id', $stationId)->where('month', $data['month'])->delete();
            collect($data['rows'])->filter(fn ($r) => filled($r['opening'] ?? null) || filled($r['closing'] ?? null))
                ->keyBy(fn ($r) => $r['product'].'|'.$r['unit'])
                ->each(fn ($r) => StationStock::query()->create([
                    'station_id' => $stationId, 'month' => $data['month'], 'product' => $r['product'], 'unit' => $r['unit'],
                    'opening' => $r['opening'] ?? null, 'closing' => $r['closing'] ?? null, 'entered_by' => $me->id,
                ]));
        });

        Activity::record('saved_monthly_stock', null, Station::query()->find($stationId)?->name.' · '.$data['month']);

        return redirect()->route('reports.stock', ['month' => $data['month']] + ($me->is_admin ? ['station_id' => $stationId] : []))->with('status', 'Stock saved.');
    }

    /** One row per product and unit: the stock entered, what was sold to whom, and whether it adds up. */
    private function rows(Station $station, string $month, bool $everyProduct): array
    {
        $start = Carbon::parse($month.'-01');
        $saved = StationStock::query()->where('station_id', $station->id)->where('month', $month)->get()->keyBy(fn ($s) => $s->product.'|'.$s->unit);
        // Last month's closing is where this month opens, until someone enters something else
        $before = StationStock::query()->where('station_id', $station->id)->where('month', $start->copy()->subMonth()->format('Y-m'))->whereNotNull('closing')->get()->keyBy(fn ($s) => $s->product.'|'.$s->unit);
        $sold = DailyReportLine::query()->join('daily_reports', 'daily_reports.id', '=', 'daily_report_lines.daily_report_id')
            ->where('daily_reports.station_id', $station->id)
            ->whereDate('daily_reports.report_date', '>=', $start)->whereDate('daily_reports.report_date', '<=', $start->copy()->endOfMonth())
            ->groupBy('product', 'unit', 'buyer')
            ->selectRaw('product, unit, buyer, sum(quantity) as quantity, sum(amount) as amount')->get()
            ->groupBy(fn ($l) => $l->product.'|'.$l->unit);

        $keys = $saved->keys()->merge($before->keys())->merge($sold->keys())->unique();
        if ($everyProduct) {
            $have = $keys->map(fn ($k) => explode('|', $k)[0]);
            $keys = $keys->merge(Product::query()->where('is_active', true)->pluck('name')->reject(fn ($p) => $have->contains($p))->map(fn ($p) => $p.'|Bags'));
        }

        return $keys->sort()->values()->map(function ($key) use ($saved, $before, $sold) {
            [$product, $unit] = explode('|', $key, 2);
            $lines = $sold->get($key, collect());
            $to = fn (?string $buyer, string $field) => (float) $lines->filter(fn ($l) => $l->buyer === $buyer)->sum($field);
            $row = $saved->get($key);
            $opening = $row?->opening ?? $before->get($key)?->closing;
            $closing = $row?->closing;
            $soldQty = (float) $lines->sum('quantity');

            return [
                'product' => $product, 'unit' => $unit, 'opening' => $opening, 'carried' => $row?->opening === null && $opening !== null, 'closing' => $closing,
                'agro_dealer' => $to('agro_dealer', 'quantity'), 'farmer' => $to('farmer', 'quantity'), 'not_split' => $to(null, 'quantity'),
                'agro_dealer_amount' => $to('agro_dealer', 'amount'), 'farmer_amount' => $to('farmer', 'amount'),
                // More than expected is usually stock received in the month; less is stock to explain
                'difference' => $opening !== null && $closing !== null ? round($closing - ($opening - $soldQty), 2) : null,
            ];
        })->all();
    }

    private function monthAllowed(string $month): bool
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 && $month >= self::FIRST_MONTH && $month <= now()->format('Y-m');
    }

    /** @return list<string> newest first */
    private function months(): array
    {
        $out = [];
        for ($m = now()->startOfMonth(); $m->format('Y-m') >= self::FIRST_MONTH; $m = $m->copy()->subMonth()) {
            $out[] = $m->format('Y-m');
        }

        return $out;
    }
}
