<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    use HasOptions;

    case Ordered = 'ordered';
    case InTransit = 'in_transit';
    case AtPort = 'at_port';
    case Clearing = 'clearing';
    case Offloading = 'offloading';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function color(): string
    {
        return match ($this) {
            self::Ordered => 'gray',
            self::InTransit => 'blue',
            self::AtPort, self::Clearing => 'amber',
            self::Offloading => 'violet',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }
}
