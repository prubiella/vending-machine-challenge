<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Product;

use App\Shared\Domain\DomainException;

final class InvalidSelectorException extends DomainException
{
    public static function fromString(string $value): self
    {
        return new self(\sprintf('A selector is made of uppercase letters, digits and underscores, got "%s".', $value));
    }
}
