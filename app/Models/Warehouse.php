<?php

namespace App\Models;

use App\Enums\WarehouseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => WarehouseType::class,
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function agroDealer(): BelongsTo
    {
        return $this->belongsTo(AgroDealer::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Places that sell to farmers (sale points, zonal stores and dealer shops).
     */
    public function scopeSellsToFarmers(Builder $query): void
    {
        $query->whereIn('type', [WarehouseType::Zonal, WarehouseType::SalePoint, WarehouseType::AgroDealer]);
    }

    public function totalTonnes(): float
    {
        return round($this->balances->sum(fn (StockBalance $b) => $b->bags * $b->batch->product->package_kg / 1000), 2);
    }

    public function utilisationPercent(): int
    {
        if ($this->capacity_tonnes <= 0) {
            return 0;
        }

        return (int) min(100, round($this->totalTonnes() / $this->capacity_tonnes * 100));
    }
}
