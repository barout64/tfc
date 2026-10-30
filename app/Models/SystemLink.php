<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A TFC person's account in one system (id and name as they are there). */
#[Fillable(['user_id', 'system', 'remote_id', 'remote_name'])]
class SystemLink extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
