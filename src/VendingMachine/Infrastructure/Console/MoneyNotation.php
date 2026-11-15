<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

final class MoneyNotation
{
    public static function coin(int $cents): string
    {
        if (0 === $cents % 100) {
            return (string) intdiv($cents, 100);
        }

        return self::amount($cents);
    }

    public static function amount(int $cents): string
    {
        return \sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
