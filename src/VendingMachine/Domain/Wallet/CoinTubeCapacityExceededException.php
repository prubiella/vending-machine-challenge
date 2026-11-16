<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Wallet;

use App\Shared\Domain\DomainException;
use App\VendingMachine\Domain\Coin\Coin;

final class CoinTubeCapacityExceededException extends DomainException
{
    public static function forCoin(Coin $coin, int $count): self
    {
        return new self(\sprintf('A coin tube holds at most %d coins, got %d of %d cents.', Wallet::TUBE_CAPACITY, $count, $coin->value));
    }
}
