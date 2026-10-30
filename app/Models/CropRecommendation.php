<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CropRecommendation extends Model
{
    protected $guarded = ['id'];

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
