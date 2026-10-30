<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $guarded = ['id'];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
