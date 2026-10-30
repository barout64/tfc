<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $guarded = ['id'];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function farmers(): HasMany
    {
        return $this->hasMany(Farmer::class);
    }
}
