<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['manufactured_on' => 'date', 'expires_on' => 'date', 'unit_cost' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function isExpiringSoon(int $days = 90): bool
    {
        return $this->expires_on !== null && $this->expires_on->lte(now()->addDays($days));
    }
}
