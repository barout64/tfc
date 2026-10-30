<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReportProductSummary extends Model
{
    protected $guarded = ['id'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(StockReport::class, 'stock_report_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
