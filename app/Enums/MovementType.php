<?php

namespace App\Enums;

enum MovementType: string
{
    use HasOptions;

    case Receipt = 'receipt';
    case DispatchOut = 'dispatch_out';
    case TransferIn = 'transfer_in';
    case Sale = 'sale';
    case Loss = 'loss';
    case Adjustment = 'adjustment';
    case Return = 'return';
}
