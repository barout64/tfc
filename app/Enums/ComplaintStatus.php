<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function color(): string
    {
        return match ($this) {
            self::Open => 'red',
            self::InProgress => 'amber',
            self::Resolved => 'green',
            self::Closed => 'gray',
        };
    }
}
