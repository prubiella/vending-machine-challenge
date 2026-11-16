<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Notation;

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

    public static function requireCoinCents(string $notation): int
    {
        return self::coinCents($notation) ?? throw ForeignCoinException::fromToken($notation);
    }

    public static function coinsAsList(): string
    {
        return self::joinCoinTokens(' and ');
    }

    public static function coinsAsChoice(): string
    {
        return self::joinCoinTokens(' or ');
    }

    public static function twoDecimals(int $cents): string
    {
        return \sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private static function joinCoinTokens(string $lastSeparator): string
    {
        $tokens = array_map(static fn (Coin $coin): string => self::coinToken($coin->value), Coin::cases());

        return implode(', ', \array_slice($tokens, 0, -1)).$lastSeparator.end($tokens);
    }
}
