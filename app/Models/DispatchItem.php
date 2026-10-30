<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchItem extends Model
{
    protected $guarded = ['id'];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    /**
     * Bags sent but not received in good condition (short + damaged).
     */
    public function lostBags(): int
    {
        if ($this->bags_received === null) {
            return 0;
        }

        return max(0, $this->bags_sent - $this->bags_received) + $this->bags_damaged;
    }
}
