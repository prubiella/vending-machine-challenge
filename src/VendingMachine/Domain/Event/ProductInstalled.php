<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\TechnicianId;

/**
 * A service gave the machine a product, or redefined one it had, with the units it starts with.
 * Few units record no StockRunningLow: that event is a sale crossing the threshold (ADR 22).
 */
final readonly class ProductInstalled implements VendingMachineEvent
{
    public function __construct(
        private TechnicianId $technicianId,
        private Product $product,
        private int $units,
    ) {
    }

    public function technicianId(): TechnicianId
    {
        return $this->technicianId;
    }

    public function product(): Product
    {
        return $this->product;
    }

    public function units(): int
    {
        return $this->units;
    }
}
