<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Command\InsertCoin;

use App\Tests\Unit\VendingMachine\Application\VendingMachineRepositoryMother;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommandHandler;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Coin\UnknownCoinException;
use PHPUnit\Framework\TestCase;

final class InsertCoinCommandHandlerTest extends TestCase
{
    public function testTheInsertedCoinJoinsTheCredit(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();
        $handler = new InsertCoinCommandHandler($repository);

        $handler(new InsertCoinCommand(25));
        $handler(new InsertCoinCommand(100));

        self::assertTrue($repository->get()->wallet()->credit()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::OneHundredCents)));
    }

    public function testRejectsACoinOfUnknownCentsAndKeepsTheCredit(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();
        $handler = new InsertCoinCommandHandler($repository);
        $handler(new InsertCoinCommand(25));

        try {
            $handler(new InsertCoinCommand(50));
            self::fail('Expected an unknown coin.');
        } catch (UnknownCoinException) {
            self::assertTrue($repository->get()->wallet()->credit()->equals(CoinCollection::of(Coin::TwentyFiveCents)));
        }
    }
}
