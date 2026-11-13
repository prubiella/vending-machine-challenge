<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Coin;

use App\Shared\Domain\DomainException;

final class CoinNotInCollectionException extends DomainException
{
    public static function forCoin(Coin $coin): self
    {
        return new self(\sprintf('There is no coin of %d cents to remove.', $coin->value));
    }
}
