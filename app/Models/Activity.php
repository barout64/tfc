<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Who signed in to the portal, opened a system, or changed someone's access — and when. */
#[Fillable(['user_id', 'action', 'system', 'detail', 'ip_address'])]
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?string $system = null, ?string $detail = null, ?User $by = null): void
    {
        static::query()->create([
            'user_id' => ($by ?? auth()->user())?->id,
            'action' => $action,
            'system' => $system,
            'detail' => $detail ? mb_substr($detail, 0, 250) : null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
