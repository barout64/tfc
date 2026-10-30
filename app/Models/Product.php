<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function bagsToTonnes(int $bags): float
    {
        return round($bags * $this->package_kg / 1000, 2);
    }

    public function tonnesToBags(float $tonnes): int
    {
        return (int) round($tonnes * 1000 / $this->package_kg);
    }

    /**
     * Active price for a region (falls back to the national price).
     */
    public function priceFor(?int $regionId = null, ?Season $season = null): ?ProductPrice
    {
        $season ??= Season::current();

        return $this->prices()
            ->where('season_id', $season->id)
            ->where(fn (Builder $q) => $q->where('region_id', $regionId)->orWhereNull('region_id'))
            ->where('effective_from', '<=', now()->toDateString())
            ->orderByRaw('region_id IS NULL')
            ->latest('effective_from')
            ->first();
    }
}
