<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\TechnicianId;

/**
 * A service took a product out of the machine, with the units its slot still held.
 */
final readonly class ProductRetired implements VendingMachineEvent
{
    public function __construct(
        private TechnicianId $technicianId,
        private Product $product,
        private int $unitsRemoved,
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

    public function unitsRemoved(): int
    {
        return $this->unitsRemoved;
    }
}
