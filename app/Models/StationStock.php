<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A station's opening and closing stock of one product for one month. */
class StationStock extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['opening' => 'float', 'closing' => 'float'];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
