<?php

namespace App\Enums;

enum DispatchStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Approved = 'approved';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved => 'violet',
            self::InTransit => 'blue',
            self::Delivered => 'green',
            self::Cancelled => 'red',
        };
    }
}
