<?php

namespace App\Models;

use App\Enums\DealerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgroDealer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DealerStatus::class,
            'license_expiry' => 'date',
            'approved_at' => 'datetime',
            'credit_limit' => 'decimal:2',
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

    /**
     * The dealer's shop, modelled as a warehouse so stock moves the same way everywhere.
     */
    public function outlet(): HasOne
    {
        return $this->hasOne(Warehouse::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isLicenseExpired(): bool
    {
        return $this->license_expiry !== null && $this->license_expiry->isPast();
    }
}
