<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\VendingMachine\Domain\Coin\Coin;

final readonly class MoneyNotation
{
    public static function coinToken(int $cents): string
    {
        if (0 === $cents % 100) {
            return (string) intdiv($cents, 100);
        }

        return self::twoDecimals($cents);
    }

    // The notation is compared with that of each coin rather than read as a number, so no float is
    // involved and only the statement's notation is accepted: 0.1 and 1.00 are not.
    public static function coinCents(string $notation): ?int
    {
        foreach (Coin::cases() as $coin) {
            if (self::coinToken($coin->value) === $notation) {
                return $coin->value;
            }
        }

        return null;
    }

    public static function twoDecimals(int $cents): string
    {
        return \sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
