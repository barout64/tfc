<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\WarehouseType;
use App\Models\District;
use App\Models\Product;
use App\Models\Region;
use App\Models\Season;
use App\Models\StockBatch;
use App\Models\StockReport;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Xlsx;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads TFC's weekly "STOCK POSITION AS AT dd/mm/yyyy" Excel (sheets STOCK POSITION,
 * SALES EXTRACT and SALES SUMMARY), stores it as a StockReport and can bring each
 * centre's bag balances in line with it.
 */
class StockPositionImporter
{
    /** Region labels used in the report that are not region names. */
    private const REGION_ALIASES = ['DSM' => 'Dar es Salaam', 'DCG' => 'Dar es Salaam', 'DAR' => 'Dar es Salaam'];

    public function __construct(private StockService $stock) {}

    /**
     * @return array{date: ?string, title: string, lines: list<array>, bulk: list<array>, summaries: list<array>}
     */
    public function parse(string $path): array
    {
        $sheets = Xlsx::read($path);
        $position = $this->sheet($sheets, 'STOCK POSITION') ?? throw new RuntimeException(__('The file has no "STOCK POSITION" sheet.'));

        $data = ['date' => null, 'title' => '', 'lines' => [], 'bulk' => [], 'summaries' => []];
        foreach ($position as $row) {
            foreach ($row as $cell) {
                if (is_string($cell) && preg_match('#AS AT\s*(\d{1,2})[/._-](\d{1,2})[/._-](\d{4})#i', $cell, $m)) {
                    $data['title'] = trim(preg_replace('/\s+/', ' ', $cell));
                    $data['date'] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                    break 2;
                }
            }
        }

        // Centre lines
        [$headerIndex, $cols] = $this->header($position, [
            'region' => '/^REGION$/i', 'center' => '/^CENT(ER|RE)$/i', 'distributed' => '/DISTRIBUTED/i', 'sold' => '/^STOCK SOLD/i',
            'balance' => '/^BALANCE/i', 'dap_old' => '/OLD STOCK/i', 'subsidy' => '/SUBSIDY/i', 'value' => '/AMOUNT|VALUE/i',
            'urea' => '/UREA/i', 'cash' => '/CASH/i',
        ], ['region', 'center', 'balance']);
        foreach (array_slice($position, $headerIndex + 1) as $row) {
            $region = trim((string) ($row[$cols['region']] ?? ''));
            $center = trim((string) ($row[$cols['center']] ?? ''));
            if (preg_match('/^TOTAL/i', $region) || preg_match('/^TOTAL/i', $center)) {
                break;
            }
            if ($region === '' || $center === '') {
                continue;
            }
            $num = fn (string $key) => isset($cols[$key]) ? $this->number($row[$cols[$key]] ?? null) : 0.0;
            $data['lines'][] = [
                'region_label' => $region, 'center_label' => $center,
                'distributed_tonnes' => round($num('distributed'), 3), 'sold_tonnes' => round($num('sold'), 3),
                'balance_tonnes' => round($num('balance'), 3), 'dap_old_stock_tonnes' => round($num('dap_old'), 3),
                'urea_tonnes' => round($num('urea'), 3), 'cash_sale_tzs' => round($num('cash'), 2),
                'subsidy_tzs' => round($num('subsidy'), 2), 'stock_value_tzs' => round($num('value'), 2),
            ];
        }
        if (! $data['lines']) {
            throw new RuntimeException(__('No centre rows were found on the "STOCK POSITION" sheet.'));
        }

        // Bulk / export sales: the "SALES SUMMARY" block on the SALES EXTRACT sheet
        if ($extract = $this->sheet($sheets, 'SALES EXTRACT')) {
            $start = null;
            foreach ($extract as $i => $row) {
                if (in_array('DETAILS', array_map(fn ($c) => strtoupper(trim((string) $c)), $row), true)) {
                    $start = $i;
                    break;
                }
            }
            if ($start !== null) {
                [, $c] = $this->header([$extract[$start]], ['details' => '/^DETAILS$/i', 'qty' => '/^STOCK/i', 'usd_rate' => '/USD PER/i', 'tzs' => '/AMOUNT \(TZS\)/i', 'usd' => '/AMOUNT \(USD\)/i'], ['details', 'qty']);
                foreach (array_slice($extract, $start + 1) as $row) {
                    $label = trim(preg_replace('/\s+/', ' ', (string) ($row[$c['details']] ?? '')));
                    if ($label === '' || preg_match('/^TOTAL/i', $label)) {
                        if ($label !== '') {
                            break;
                        }

                        continue;
                    }
                    if (preg_match('/TFC CENT/i', $label)) {
                        continue; // the centres' own sales are the lines above
                    }
                    $data['bulk'][] = [
                        'description' => $label,
                        'product_code' => $this->productCode($label),
                        'buyer' => preg_match('/\bTO\s+([A-Z0-9 .&-]+?)\s*(\(|$)/i', $label, $m) ? Str::title(trim($m[1])) : null,
                        'quantity_tonnes' => round($this->number($row[$c['qty']] ?? null), 3),
                        'usd_per_tonne' => isset($c['usd_rate']) && $this->number($row[$c['usd_rate']] ?? null) ? $this->number($row[$c['usd_rate']]) : null,
                        'amount_usd' => isset($c['usd']) && $this->number($row[$c['usd']] ?? null) ? $this->number($row[$c['usd']]) : null,
                        'amount_tzs' => isset($c['tzs']) && $this->number($row[$c['tzs']] ?? null) ? $this->number($row[$c['tzs']]) : null,
                    ];
                }
            }
        }

        // Product summary sheet
        if ($summary = $this->sheet($sheets, 'SALES SUMMARY')) {
            [$h, $c] = $this->header($summary, ['sold' => '/STOCK SOLD/i', 'cash' => '/CASH SALE/i', 'subsidy' => '/SUBSIDY/i', 'balance' => '/STOCK BALANCE/i', 'value' => '/VALUE OF STOCK/i'], ['sold', 'balance']);
            foreach (array_slice($summary, $h + 1) as $i => $row) {
                $label = trim((string) ($row[0] ?? ''));
                if ($label === '' || ! $this->productCode($label)) {
                    continue;
                }
                [$cash, $cashCur] = $this->money($row[$c['cash']] ?? null);
                [$value, $valueCur] = $this->money($row[$c['value']] ?? null);
                $data['summaries'][] = [
                    'label' => $label, 'product_code' => $this->productCode($label), 'sort' => $i,
                    'sold_tonnes' => round($this->number($row[$c['sold']] ?? null), 3),
                    'cash_sale_amount' => $cash, 'cash_sale_currency' => $cashCur,
                    'subsidy_tzs' => round($this->number($row[$c['subsidy']] ?? null), 2),
                    'balance_tonnes' => round($this->number($row[$c['balance']] ?? null), 3),
                    'stock_value_amount' => $value, 'stock_value_currency' => $valueCur,
                ];
            }
        }

        return $data;
    }

    /** Save a parsed report; centres missing from the system are created as sale points. */
    public function save(array $data, ?User $user, ?string $sourceFile = null, ?string $date = null): StockReport
    {
        $date ??= $data['date'] ?? throw new RuntimeException(__('The report date could not be read from the file. Enter it on the form.'));

        return DB::transaction(function () use ($data, $user, $sourceFile, $date) {
            $report = StockReport::create([
                'report_date' => $date,
                'title' => $data['title'] ?: 'Stock position as at '.Carbon::parse($date)->format('d/m/Y'),
                'source_file' => $sourceFile,
                'created_by' => $user?->id,
            ]);
            foreach ($data['lines'] as $i => $line) {
                $report->lines()->create($line + ['warehouse_id' => $this->centre($line['region_label'], $line['center_label'])->id, 'sort' => $i]);
            }
            $products = Product::pluck('id', 'code');
            foreach ($data['bulk'] as $sale) {
                $report->bulkSales()->create(collect($sale)->except('product_code')->all() + ['product_id' => $products[$sale['product_code']] ?? null]);
            }
            foreach ($data['summaries'] as $row) {
                $report->productSummaries()->create(collect($row)->except('product_code')->all() + ['product_id' => $products[$row['product_code']] ?? null]);
            }

            return $report;
        });
    }

    /**
     * Bring every centre's bags in line with the report (50 kg bags; 1 tonne = 20 bags):
     * DAP of this season, DAP old stock and UREA. The first time a centre is seen the
     * stock is recorded as received (distributed) and sold; later reports adjust the difference.
     *
     * @return int number of ledger movements written
     */
    public function applyToStock(StockReport $report, ?User $user): int
    {
        $season = Season::current();
        $dap = Product::where('code', 'DAP')->firstOrFail();
        $urea = Product::where('code', 'UREA')->firstOrFail();
        $batches = [
            'dap' => StockBatch::firstOrCreate(['product_id' => $dap->id, 'batch_number' => 'DAP-'.str_replace('/', '-', $season->name)], ['source' => 'import']),
            'dap_old' => StockBatch::firstOrCreate(['product_id' => $dap->id, 'batch_number' => 'DAP-OLD-STOCK'], ['source' => 'import']),
            'urea' => StockBatch::firstOrCreate(['product_id' => $urea->id, 'batch_number' => 'UREA-'.str_replace('/', '-', $season->name)], ['source' => 'import']),
        ];
        $asAt = $report->report_date->format('d/m/Y');
        $moves = 0;

        DB::transaction(function () use ($report, $user, $batches, $dap, $urea, $asAt, &$moves) {
            foreach ($report->lines()->with('warehouse')->get() as $line) {
                $centre = $line->warehouse;
                if (! $centre) {
                    continue;
                }
                $targets = [
                    'dap' => $dap->tonnesToBags((float) $line->balance_tonnes),
                    'dap_old' => $dap->tonnesToBags((float) $line->dap_old_stock_tonnes),
                    'urea' => $urea->tonnesToBags((float) $line->urea_tonnes),
                ];
                foreach ($targets as $key => $target) {
                    $batch = $batches[$key];
                    $current = $this->stock->balance($centre, $batch);
                    $fresh = ! $centre->movements()->where('stock_batch_id', $batch->id)->exists();

                    if ($fresh && $key === 'dap') {
                        $received = max($target, $dap->tonnesToBags((float) $line->distributed_tonnes));
                        if ($received > 0) {
                            $this->stock->add($centre, $batch, $received, MovementType::Receipt, $report, "Stock distributed to {$centre->name} (stock position as at {$asAt})");
                            $moves++;
                        }
                        if ($received - $target > 0) {
                            $this->stock->remove($centre, $batch, $received - $target, MovementType::Sale, $report, number_format((float) $line->sold_tonnes, 2)." t sold at {$centre->name} (stock position as at {$asAt})");
                            $moves++;
                        }
                    } elseif ($fresh && $target > 0) {
                        $this->stock->add($centre, $batch, $target, MovementType::Receipt, $report, "Opening balance (stock position as at {$asAt})");
                        $moves++;
                    } elseif ($target !== $current) {
                        $target > $current
                            ? $this->stock->add($centre, $batch, $target - $current, MovementType::Adjustment, $report, "Matched to stock position as at {$asAt}")
                            : $this->stock->remove($centre, $batch, $current - $target, MovementType::Adjustment, $report, "Matched to stock position as at {$asAt}");
                        $moves++;
                    }
                }
            }
            $report->update(['applied_to_stock_at' => now(), 'applied_by' => $user?->id]);
        });

        return $moves;
    }

    /** Find the centre (warehouse) for a report row, creating it the first time. */
    private function centre(string $regionLabel, string $centerLabel): Warehouse
    {
        $region = $this->region($regionLabel);
        $name = Str::title(trim($centerLabel));
        $existing = Warehouse::where('region_id', $region->id)->whereRaw('LOWER(name) IN (?, ?)', [mb_strtolower($name), mb_strtolower('TFC '.$name)])->first();
        if ($existing) {
            return $existing;
        }

        $base = 'TFC-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 4));
        $code = $base;
        for ($i = 2; Warehouse::where('code', $code)->exists(); $i++) {
            $code = $base.$i;
        }

        return Warehouse::create([
            'code' => $code,
            'name' => $name,
            'type' => WarehouseType::SalePoint,
            'region_id' => $region->id,
            'district_id' => District::where('region_id', $region->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id'),
            'lat' => $region->lat,
            'lng' => $region->lng,
            'address' => $name.', '.$region->name,
            'is_public' => true,
            'is_active' => true,
        ]);
    }

    private function region(string $label): Region
    {
        $clean = trim(preg_replace('/\(.*\)/', '', $label));
        $name = self::REGION_ALIASES[strtoupper($clean)] ?? $clean;

        return Region::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? throw new RuntimeException(__('Unknown region ":region" in the report. Add it under Master data first.', ['region' => $label]));
    }

    private function sheet(array $sheets, string $name): ?array
    {
        foreach ($sheets as $title => $rows) {
            if (strcasecmp(trim($title), $name) === 0) {
                return $rows;
            }
        }

        return null;
    }

    /**
     * Locate the header row and the column of each field.
     *
     * @param  array<string, string>  $patterns
     * @return array{0: int, 1: array<string, int>}
     */
    private function header(array $rows, array $patterns, array $required): array
    {
        foreach ($rows as $i => $row) {
            $cols = [];
            foreach ($patterns as $key => $pattern) {
                foreach ($row as $j => $cell) {
                    if (is_string($cell) && ! in_array($j, $cols, true) && preg_match($pattern, trim($cell))) {
                        $cols[$key] = $j;
                        break;
                    }
                }
            }
            if (! array_diff($required, array_keys($cols))) {
                return [$i, $cols];
            }
        }

        throw new RuntimeException(__('Could not find the column headings (:cols) in the file.', ['cols' => implode(', ', array_map('strtoupper', $required))]));
    }

    private function number(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    /** "12440855USD" → [12440855, 'USD']; plain numbers are TZS. */
    private function money(mixed $value): array
    {
        $currency = is_string($value) && stripos($value, 'USD') !== false ? 'USD' : 'TZS';

        return [round($this->number($value), 2), $currency];
    }

    private function productCode(string $label): ?string
    {
        return preg_match('/\b(DAP|NPK|UREA|CAN|SA)\b/', strtoupper($label), $m) ? $m[1] : null;
    }
}
