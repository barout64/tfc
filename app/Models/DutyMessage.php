<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** On a task: a reply, the work handed in (submission, with its file), or a note of what happened. */
#[Fillable(['task_id', 'user_id', 'kind', 'body', 'attachment_path', 'attachment_name'])]
class DutyMessage extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(DutyTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
