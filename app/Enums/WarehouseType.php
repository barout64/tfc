<?php

namespace App\Enums;

enum WarehouseType: string
{
    use HasOptions;

    case Port = 'port';
    case Hq = 'hq';
    case Zonal = 'zonal';
    case SalePoint = 'sale_point';
    case AgroDealer = 'agro_dealer';

    public function label(): string
    {
        return match ($this) {
            self::Hq => __('Head office (HQ)'),
            self::SalePoint => __('Sale point'),
            self::AgroDealer => __('Agro-dealer outlet'),
            default => __(ucfirst($this->value)),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Port => '#0ea5e9',
            self::Hq => '#7c3aed',
            self::Zonal => '#16a34a',
            self::SalePoint => '#f59e0b',
            self::AgroDealer => '#e11d48',
        };
    }
}
