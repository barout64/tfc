<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** Uwajibikaji: one task given to one person, with a deadline. */
#[Fillable(['assigned_by', 'assigned_to', 'title', 'details', 'due_at', 'status', 'seen_at', 'started_at', 'submitted_at', 'approved_at', 'missed_at'])]
class DutyTask extends Model
{
    public const TZ = 'Africa/Dar_es_Salaam';

    /** new → seen (opened it) → on_it → submitted (waiting for approval) → approved; or sent back to work on again. */
    public const STATUSES = [
        'new' => 'New', 'seen' => 'Seen', 'on_it' => 'On it', 'stuck' => 'Needs help',
        'submitted' => 'Waiting for approval', 'returned' => 'Sent back', 'approved' => 'Approved', 'cancelled' => 'Cancelled',
    ];

    /** Still to be done by the person: these miss the deadline when it passes. */
    public const TO_DO = ['new', 'seen', 'on_it', 'stuck', 'returned'];

    /** Not finished: to do, or handed in and waiting for approval. */
    public const ACTIVE = ['new', 'seen', 'on_it', 'stuck', 'returned', 'submitted'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'seen_at' => 'datetime', 'started_at' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'missed_at' => 'datetime'];
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DutyMessage::class, 'task_id')->orderBy('id');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(DutyWarning::class, 'task_id')->orderBy('id');
    }

    public function number(): string
    {
        return 'UW-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function isToDo(): bool
    {
        return in_array($this->status, self::TO_DO, true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function isOverdue(): bool
    {
        return $this->isToDo() && $this->due_at->isPast();
    }

    /** The one who gave it, the one who does it, and administrators. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->is_admin ? $query : $query->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('assigned_by', $user->id));
    }

    public function canSee(User $user): bool
    {
        return $user->is_admin || in_array($user->id, [$this->assigned_to, $this->assigned_by], true);
    }

    /** The one who gave it (or an administrator) approves, sends back, sets a new deadline and decides on explanations. */
    public function canLead(User $user): bool
    {
        return $user->is_admin || $user->id === $this->assigned_by;
    }

    public function note(string $body, ?User $by = null, string $kind = 'event'): DutyMessage
    {
        return $this->messages()->create(['user_id' => ($by ?? auth()->user())?->id, 'kind' => $kind, 'body' => $body]);
    }

    public function dueLabel(): string
    {
        return $this->due_at->timezone(self::TZ)->format('D d M Y, H:i');
    }

    /**
     * Every deadline that has passed with the work not handed in becomes a warning for the person (once per deadline).
     * Runs on each request; cheap when nothing is due.
     */
    public static function sweep(): int
    {
        $made = 0;
        $due = static::query()->whereIn('status', self::TO_DO)->whereNull('missed_at')->where('due_at', '<=', now())->get();
        foreach ($due as $task) {
            DB::transaction(function () use ($task, &$made) {
                // claim it first, so two requests at the same moment never give two warnings
                if (static::query()->whereKey($task->id)->whereNull('missed_at')->update(['missed_at' => now()]) !== 1) {
                    return;
                }
                $warning = DutyWarning::give($task);
                // written by nobody: the portal itself
                $task->messages()->create(['user_id' => null, 'kind' => 'event', 'body' => $warning->final
                    ? "The deadline passed without the work. Warning {$warning->number} of ".DutyWarning::MAX." for {$task->assignee->name}: no more explaining — only the administrator can clear the warnings."
                    : "The deadline passed without the work. Warning {$warning->number} of ".DutyWarning::MAX." for {$task->assignee->name}, who must explain why before working on again."]);
                $made++;
            });
        }

        return $made;
    }
}
