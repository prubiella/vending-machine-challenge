<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Product;

use App\Shared\Domain\DomainException;

final class EmptyProductNameException extends DomainException
{
    public static function forSelector(Selector $selector): self
    {
        return new self(\sprintf('The product of selector %s needs a name.', $selector->value()));
    }
}
