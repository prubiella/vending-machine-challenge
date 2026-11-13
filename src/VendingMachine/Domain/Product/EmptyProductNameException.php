<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Product;

use App\Shared\Domain\DomainException;

final class EmptyProductNameException extends DomainException
{
    public static function fromString(string $value): self
    {
        return new self(\sprintf('A product name cannot be blank, got "%s".', $value));
    }
}
