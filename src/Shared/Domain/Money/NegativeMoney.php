<?php

declare(strict_types=1);

namespace App\Shared\Domain\Money;

use App\Shared\Domain\DomainException;

final class NegativeMoney extends DomainException
{
    public static function fromCents(int $cents): self
    {
        return new self(\sprintf('Money cannot be negative, got %d cents.', $cents));
    }
}
