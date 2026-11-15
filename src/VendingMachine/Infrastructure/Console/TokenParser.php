<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\Command;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Domain\Coin\Coin;

final readonly class TokenParser
{
    private const string SELECT_PREFIX = 'GET-';
    private const string RETURN_COINS = 'RETURN-COIN';

    public function parse(string $token): Command
    {
        if (self::RETURN_COINS === $token) {
            return new ReturnCoinsCommand();
        }

        if (self::isSelection($token)) {
            return new SelectProductCommand(substr($token, \strlen(self::SELECT_PREFIX)));
        }

        if (is_numeric($token)) {
            return new InsertCoinCommand(self::coinCents($token));
        }

        throw RejectedTokenException::unknownAction($token);
    }

    private static function isSelection(string $token): bool
    {
        return str_starts_with($token, self::SELECT_PREFIX) && \strlen($token) > \strlen(self::SELECT_PREFIX);
    }

    // The token is compared with the notation of each coin rather than read as a number, so no float
    // is involved and only the statement's notation is accepted: 0.1 and 1.00 are not.
    private static function coinCents(string $token): int
    {
        foreach (Coin::cases() as $coin) {
            if (MoneyNotation::coinToken($coin->value) === $token) {
                return $coin->value;
            }
        }

        throw RejectedTokenException::foreignCoin($token);
    }
}
