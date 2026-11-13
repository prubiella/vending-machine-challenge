<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\Shared\Domain\DomainException;
use App\VendingMachine\Domain\Product\Selector;

final class DuplicateSelectorException extends DomainException
{
    public static function forSelector(Selector $selector): self
    {
        return new self(\sprintf('The selector %s is used by more than one slot.', $selector->value()));
    }
}
