<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Change;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;

interface ChangeMaker
{
    public function gives(Coin $coin): bool;

    public function makeChange(Money $amount, CoinCollection $available): CoinCollection;
}
