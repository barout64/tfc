<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reply on a helpdesk request (with an optional photo or file), or a note of what happened (taken, fixed…). */
#[Fillable(['ticket_id', 'user_id', 'kind', 'body', 'attachment_path', 'attachment_name'])]
class HelpdeskMessage extends Model
{
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
