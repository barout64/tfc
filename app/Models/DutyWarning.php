<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A missed deadline. explain: the person must explain before working on again → review: the one who gave the task
 * decides → kept (it counts) or removed (struck off). cleared: the administrator reset it. The fifth is final: kept
 * straight away, no explaining; the person stays locked until the administrator clears their warnings.
 */
#[Fillable(['user_id', 'task_id', 'due_at', 'number', 'final', 'status', 'explanation', 'explained_at', 'decided_by', 'decision', 'decision_note', 'decided_at', 'cleared_by', 'clear_reason', 'cleared_at'])]
class DutyWarning extends Model
{
    public const MAX = 5;

    /** These count towards the five. */
    public const COUNTING = ['explain', 'review', 'kept'];

    public const STATUSES = ['explain' => 'Waiting for the explanation', 'review' => 'Explained — waiting for a decision', 'kept' => 'Warning stands', 'removed' => 'Struck off', 'cleared' => 'Cleared by the administrator'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'final' => 'boolean', 'explained_at' => 'datetime', 'decided_at' => 'datetime', 'cleared_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DutyTask::class, 'task_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function clearer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function scopeCounting(Builder $query): Builder
    {
        return $query->whereIn('status', self::COUNTING);
    }

    public function counts(): bool
    {
        return in_array($this->status, self::COUNTING, true);
    }

    public static function countFor(User|int $user): int
    {
        return static::query()->where('user_id', $user instanceof User ? $user->id : $user)->counting()->count();
    }

    /** Locked: a missed deadline not yet explained, or five warnings. They may only read and explain. */
    public static function lockFor(User $user): ?string
    {
        if (static::countFor($user) >= self::MAX) {
            return 'five';
        }

        return static::query()->where('user_id', $user->id)->where('status', 'explain')->exists() ? 'explain' : null;
    }

    /** A new warning for the person doing $task: the next number; the fifth (or later) is final. */
    public static function give(DutyTask $task): self
    {
        $number = static::countFor($task->assigned_to) + 1;

        return static::query()->create([
            'user_id' => $task->assigned_to, 'task_id' => $task->id, 'due_at' => $task->due_at,
            'number' => min($number, 255), 'final' => $number >= self::MAX, 'status' => $number >= self::MAX ? 'kept' : 'explain',
        ]);
    }
}
