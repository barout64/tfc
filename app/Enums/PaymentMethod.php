<?php

namespace App\Enums;

enum PaymentMethod: string
{
    use HasOptions;

    case Cash = 'cash';
    case Mpesa = 'mpesa';
    case AirtelMoney = 'airtel_money';
    case Mixx = 'mixx';
    case HaloPesa = 'halopesa';
    case Gepg = 'gepg';
    case Bank = 'bank';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Mpesa => 'M-Pesa',
            self::AirtelMoney => 'Airtel Money',
            self::Mixx => 'Mixx by Yas',
            self::HaloPesa => 'HaloPesa',
            self::Gepg => 'GePG Control Number',
            default => __(ucfirst($this->value)),
        };
    }

    public function isMobileMoney(): bool
    {
        return in_array($this, [self::Mpesa, self::AirtelMoney, self::Mixx, self::HaloPesa], true);
    }
}
