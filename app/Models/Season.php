<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public static function current(): self
    {
        return once(fn () => static::where('is_active', true)->latest('starts_on')->first()
            ?? static::latest('starts_on')->firstOrFail());
    }
}
