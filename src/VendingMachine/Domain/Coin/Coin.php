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

    public static function fromCents(int $cents): self
    {
        return self::tryFrom($cents) ?? throw UnknownCoinException::fromCents($cents);
    }

    public static function smallest(): self
    {
        $cases = self::cases();
        usort($cases, static fn (self $one, self $other): int => $one->value <=> $other->value);

        return $cases[0];
    }

    public function amount(): Money
    {
        return Money::fromCents($this->value);
    }
}
