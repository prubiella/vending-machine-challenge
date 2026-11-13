<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Coin;

use App\Shared\Domain\Money\Money;

enum Coin: int
{
    case FiveCents = 5;
    case TenCents = 10;
    case TwentyFiveCents = 25;
    case OneHundredCents = 100;

    public function amount(): Money
    {
        return Money::fromCents($this->value);
    }
}
