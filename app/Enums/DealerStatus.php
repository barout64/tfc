<?php

namespace App\Enums;

enum DealerStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Suspended = 'suspended';
    case Rejected = 'rejected';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Suspended => 'gray',
            self::Rejected => 'red',
        };
    }
}
