<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A TFC stock position report ("STOCK POSITION AS AT ..."), stored exactly as reported.
 */
class StockReport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'applied_to_stock_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockReportLine::class)->orderBy('sort');
    }

    public function bulkSales(): HasMany
    {
        return $this->hasMany(StockReportBulkSale::class);
    }

    public function productSummaries(): HasMany
    {
        return $this->hasMany(StockReportProductSummary::class)->orderBy('sort');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function latestReport(): ?self
    {
        return static::latest('report_date')->latest('id')->first();
    }

    /**
     * Column totals for the centre lines, plus the national balance after bulk DAP sales
     * (e.g. DAP sold to Burundi is taken from the centres' balance).
     *
     * @return array<string, float>
     */
    public function totals(): array
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        $sum = fn (string $column) => round((float) $lines->sum($column), 3);
        $totals = [
            'distributed_tonnes' => $sum('distributed_tonnes'),
            'sold_tonnes' => $sum('sold_tonnes'),
            'balance_tonnes' => $sum('balance_tonnes'),
            'dap_old_stock_tonnes' => $sum('dap_old_stock_tonnes'),
            'urea_tonnes' => $sum('urea_tonnes'),
            'cash_sale_tzs' => round((float) $lines->sum('cash_sale_tzs'), 2),
            'subsidy_tzs' => round((float) $lines->sum('subsidy_tzs'), 2),
            'stock_value_tzs' => round((float) $lines->sum('stock_value_tzs'), 2),
        ];
        $bulk = $this->relationLoaded('bulkSales') ? $this->bulkSales : $this->bulkSales()->with('product')->get();
        $totals['dap_bulk_sold_tonnes'] = round((float) $bulk->filter(fn ($s) => $s->product?->code === 'DAP')->sum('quantity_tonnes'), 3);
        $totals['actual_dap_balance_tonnes'] = round($totals['balance_tonnes'] - $totals['dap_bulk_sold_tonnes'], 3);

        return $totals;
    }
}
