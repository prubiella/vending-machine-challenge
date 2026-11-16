<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\Shared\Domain\DomainException;
use App\VendingMachine\Domain\Product\Selector;

final class SlotCapacityExceededException extends DomainException
{
    public static function forSelector(Selector $selector, int $units): self
    {
        return new self(\sprintf('A slot holds at most %d units, got %d of %s.', Slot::CAPACITY, $units, $selector->value()));
    }
}
