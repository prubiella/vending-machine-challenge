<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\Shared\Domain\DomainException;
use App\VendingMachine\Domain\Product\Selector;

final class SoldOutException extends DomainException
{
    public static function forSelector(Selector $selector): self
    {
        return new self(\sprintf('The product of selector %s is sold out.', $selector->value()));
    }
}
