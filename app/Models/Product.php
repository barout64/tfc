<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A product that a station may report selling (from the TFC Kilimo product list). */
class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
