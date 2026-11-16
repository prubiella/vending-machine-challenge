<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\Command;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Infrastructure\Notation\MoneyNotation;

final readonly class TokenParser
{
    public const string SERVICE = 'SERVICE';
    public const string QUIT = 'QUIT';
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
            return new InsertCoinCommand(MoneyNotation::requireCoinCents($token));
        }

        throw RejectedTokenException::unknownAction($token, self::usage());
    }

    public static function sessionUsage(): string
    {
        return \sprintf('One action per line: %s. %s services the machine and %s leaves.', self::usage(), self::SERVICE, self::QUIT);
    }

    private static function usage(): string
    {
        return \sprintf('insert %s, %s<SELECTOR> or %s', self::coinsAsChoice(), self::SELECT_PREFIX, self::RETURN_COINS);
    }

    private static function coinsAsChoice(): string
    {
        return MoneyNotation::coinTokensEndingWith(' or ');
    }

    private static function isSelection(string $token): bool
    {
        return str_starts_with($token, self::SELECT_PREFIX) && \strlen($token) > \strlen(self::SELECT_PREFIX);
    }
}
