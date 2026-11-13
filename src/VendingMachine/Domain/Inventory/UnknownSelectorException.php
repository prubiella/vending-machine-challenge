<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\Shared\Domain\DomainException;
use App\VendingMachine\Domain\Product\Selector;

final class UnknownSelectorException extends DomainException
{
    public static function forSelector(Selector $selector): self
    {
        return new self(\sprintf('There is no product with selector %s.', $selector->value()));
    }
}
