<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'capacity_tonnes' => 'decimal:2'];
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }
}
