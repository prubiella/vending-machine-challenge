<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Change;

use App\Shared\Domain\DomainException;
use App\Shared\Domain\Money\Money;

final class NoExactChangeException extends DomainException
{
    public static function forAmount(Money $amount): self
    {
        return new self(\sprintf('Cannot give exactly %d cents with the coins available.', $amount->cents()));
    }
}
