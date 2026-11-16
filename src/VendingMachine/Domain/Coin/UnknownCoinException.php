<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Coin;

use App\Shared\Domain\DomainException;

final class UnknownCoinException extends DomainException
{
    public static function fromCents(int $cents): self
    {
        return new self(\sprintf('There is no coin of %d cents.', $cents));
    }
}
