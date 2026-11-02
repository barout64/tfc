<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One request for help from a TFC staff member, and what support did about it. */
#[Fillable(['user_id', 'assigned_to', 'category', 'priority', 'subject', 'description', 'location', 'phone', 'status', 'resolved_at', 'closed_at'])]
class HelpdeskTicket extends Model
{
    public const CATEGORIES = [
        'printer' => 'Printer or scanner',
        'computer' => 'Computer or laptop',
        'internet' => 'Internet or network',
        'email' => 'Email',
        'phone' => 'Phone or extension',
        'system' => 'A TFC system (Meetings, Protocol…)',
        'account' => 'Password or account',
        'software' => 'Other software',
        'equipment' => 'Other office equipment',
        'other' => 'Something else',
    ];

    public const PRIORITIES = ['low' => 'Can wait', 'normal' => 'Normal', 'urgent' => 'Urgent — I cannot work'];

    /** open → in_progress (support took it) → resolved (support says fixed) → closed (the person confirms); waiting = support needs something from them. */
    public const STATUSES = ['open' => 'New', 'in_progress' => 'Being fixed', 'waiting' => 'Waiting for you', 'resolved' => 'Fixed', 'closed' => 'Closed'];

    public const OPEN = ['open', 'in_progress', 'waiting'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HelpdeskMessage::class, 'ticket_id')->orderBy('id');
    }

    public function number(): string
    {
        return 'HD-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** What this person may see: their own requests; support and administrators see all. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSupport() ? $query : $query->where('user_id', $user->id);
    }

    public function note(string $body, ?User $by = null, string $kind = 'event'): HelpdeskMessage
    {
        return $this->messages()->create(['user_id' => ($by ?? auth()->user())?->id, 'kind' => $kind, 'body' => $body]);
    }
}
