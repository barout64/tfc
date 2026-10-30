<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dispatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DispatchStatus::class,
            'planned_date' => 'date',
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_ping_at' => 'datetime',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'transport_cost' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispatchItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TrackingEvent::class)->latest('recorded_at');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(TransporterPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalBags(): int
    {
        return (int) $this->items->sum('bags_sent');
    }

    public function totalTonnes(): float
    {
        return round($this->items->sum(fn (DispatchItem $item) => $item->bags_sent * $item->batch->product->package_kg / 1000), 2);
    }

    public function lostBags(): int
    {
        return (int) $this->items->sum(fn (DispatchItem $item) => $item->lostBags());
    }

    public function isLate(): bool
    {
        if ($this->expected_arrival_at === null) {
            return false;
        }

        if ($this->status === DispatchStatus::InTransit) {
            return $this->expected_arrival_at->isPast();
        }

        return $this->status === DispatchStatus::Delivered && $this->delivered_at?->gt($this->expected_arrival_at);
    }

    /**
     * Truck has not reported a position for too long while on the road.
     */
    public function isSilent(int $hours = 6): bool
    {
        return $this->status === DispatchStatus::InTransit
            && ($this->last_ping_at ?? $this->dispatched_at)?->lt(now()->subHours($hours));
    }
}
