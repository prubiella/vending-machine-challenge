<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\Shared\Domain\DomainException;

final class InventoryCapacityExceededException extends DomainException
{
    public static function forProducts(int $products): self
    {
        return new self(\sprintf('The machine holds at most %d products, got %d.', Inventory::CAPACITY, $products));
    }
}
