<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Application\Command\SelectProduct;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\VendingMachineRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SelectProductCommandHandlerTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testASaleThroughTheBusDropsTheProductAndItsChangeInTheTray(): void
    {
        $container = self::getContainer();
        $commandBus = $container->get(CommandBus::class);

        $commandBus->dispatch(new InsertCoinCommand(100));
        $commandBus->dispatch(new SelectProductCommand('WATER'));

        $tray = $container->get(VendingMachineRepository::class)->get()->tray();
        self::assertCount(1, $tray->products());
        self::assertTrue($tray->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents)));
    }
}
