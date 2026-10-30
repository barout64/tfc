<?php

namespace App\Enums;

enum ComplaintCategory: string
{
    use HasOptions;

    case Overpricing = 'overpricing';
    case StockOut = 'stock_out';
    case Quality = 'quality';
    case DealerConduct = 'dealer_conduct';
    case Delivery = 'delivery';
    case Other = 'other';
}
