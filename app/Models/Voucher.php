<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => VoucherStatus::class, 'expires_at' => 'datetime', 'redeemed_at' => 'datetime'];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function redeemedAt(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'redeemed_at_warehouse_id');
    }

    public function isUsable(): bool
    {
        return $this->status === VoucherStatus::Issued && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
