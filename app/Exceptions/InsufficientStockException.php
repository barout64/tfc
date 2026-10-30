<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function for(string $productName, string $warehouseName, int $requested, int $available): self
    {
        return new self(__('Not enough :product at :warehouse. Requested :requested bags, available :available bags.', [
            'product' => $productName,
            'warehouse' => $warehouseName,
            'requested' => number_format($requested),
            'available' => number_format($available),
        ]));
    }
}
