<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'eta' => 'date',
            'arrived_on' => 'date',
            'expires_on' => 'date',
            'ordered_tonnes' => 'decimal:2',
            'unit_cost_per_tonne' => 'decimal:2',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receivingWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'receiving_warehouse_id');
    }

    public function receivedTonnes(): float
    {
        return $this->product->bagsToTonnes($this->bags_received);
    }

    public function isDelayed(): bool
    {
        return $this->eta !== null && $this->arrived_on === null && $this->eta->isPast()
            && ! in_array($this->status, [ShipmentStatus::Completed, ShipmentStatus::Cancelled], true);
    }
}
