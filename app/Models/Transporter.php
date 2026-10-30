<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transporter extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['contract_expires_on' => 'date', 'rate_per_tonne_km' => 'decimal:2', 'loss_penalty_per_bag' => 'decimal:2'];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransporterPayment::class);
    }
}
