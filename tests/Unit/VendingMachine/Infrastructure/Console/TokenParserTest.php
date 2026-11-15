<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\Command;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Infrastructure\Console\RejectedTokenException;
use App\VendingMachine\Infrastructure\Console\TokenParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Command}>
     */
    public static function actions(): iterable
    {
        yield '0.05' => ['0.05', new InsertCoinCommand(5)];
        yield '0.10' => ['0.10', new InsertCoinCommand(10)];
        yield '0.25' => ['0.25', new InsertCoinCommand(25)];
        yield '1' => ['1', new InsertCoinCommand(100)];
        yield 'GET-WATER' => ['GET-WATER', new SelectProductCommand('WATER')];
        yield 'GET-SODA' => ['GET-SODA', new SelectProductCommand('SODA')];
        yield 'GET-COFFEE' => ['GET-COFFEE', new SelectProductCommand('COFFEE')];
        yield 'RETURN-COIN' => ['RETURN-COIN', new ReturnCoinsCommand()];
    }

    #[DataProvider('actions')]
    public function testTurnsATokenIntoItsCommand(string $token, Command $command): void
    {
        self::assertEquals($command, new TokenParser()->parse($token));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignCoins(): iterable
    {
        yield '0.50' => ['0.50'];
        yield '0.01' => ['0.01'];
        yield '2' => ['2'];
        yield '0.1' => ['0.1'];
    }

    #[DataProvider('foreignCoins')]
    public function testRejectsACoinTheMachineDoesNotAccept(string $token): void
    {
        $this->expectException(RejectedTokenException::class);
        $this->expectExceptionMessageIs(\sprintf('The machine does not accept a coin of %s: it takes 0.05, 0.10, 0.25 and 1.', $token));

        new TokenParser()->parse($token);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownActions(): iterable
    {
        yield 'a word' => ['HELLO'];
        yield 'GET without a selector' => ['GET-'];
        yield 'an empty token' => [''];
    }

    #[DataProvider('unknownActions')]
    public function testRejectsAnUnknownAction(string $token): void
    {
        $this->expectException(RejectedTokenException::class);
        $this->expectExceptionMessageIs(\sprintf('Unknown action "%s": insert 0.05, 0.10, 0.25 or 1, GET-<SELECTOR> or RETURN-COIN.', $token));

        new TokenParser()->parse($token);
    }
}
