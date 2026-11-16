<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Coin;

use App\Shared\Domain\DomainException;

final class NegativeCoinCountException extends DomainException
{
    public static function forCoin(Coin $coin, int $count): self
    {
        return new self(\sprintf('A coin of %d cents cannot have a negative count, got %d.', $coin->value, $count));
    }
}
