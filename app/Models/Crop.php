<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crop extends Model
{
    protected $guarded = ['id'];

    public function recommendations(): HasMany
    {
        return $this->hasMany(CropRecommendation::class);
    }
}
