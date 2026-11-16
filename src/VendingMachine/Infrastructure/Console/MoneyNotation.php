<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

final readonly class MoneyNotation
{
    public static function coinToken(int $cents): string
    {
        if (0 === $cents % 100) {
            return (string) intdiv($cents, 100);
        }

        return self::twoDecimals($cents);
    }

    public static function twoDecimals(int $cents): string
    {
        return \sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
