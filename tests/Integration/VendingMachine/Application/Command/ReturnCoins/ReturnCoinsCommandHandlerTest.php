<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Application\Command\ReturnCoins;

use App\Shared\Application\Bus\CommandBus;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\VendingMachineRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReturnCoinsCommandHandlerTest extends KernelTestCase
{
    public function testTheCommandBusRoutesTheCommandToItsHandler(): void
    {
        $container = self::getContainer();
        $commandBus = $container->get(CommandBus::class);

        $commandBus->dispatch(new InsertCoinCommand(10));
        $commandBus->dispatch(new InsertCoinCommand(10));
        $commandBus->dispatch(new ReturnCoinsCommand());

        $machine = $container->get(VendingMachineRepository::class)->get();
        self::assertTrue($machine->tray()->coins()->equals(CoinCollection::of(Coin::TenCents, Coin::TenCents)));
        self::assertTrue($machine->wallet()->credit()->isEmpty());
    }
}
