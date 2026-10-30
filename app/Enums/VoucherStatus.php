<?php

namespace App\Enums;

enum VoucherStatus: string
{
    use HasOptions;

    case Issued = 'issued';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function color(): string
    {
        return match ($this) {
            self::Issued => 'blue',
            self::Redeemed => 'green',
            self::Expired => 'gray',
            self::Cancelled => 'red',
        };
    }
}
