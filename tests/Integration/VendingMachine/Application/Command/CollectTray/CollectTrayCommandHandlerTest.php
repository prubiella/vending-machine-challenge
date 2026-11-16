<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Application\Command\CollectTray;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\CollectTray\CollectTrayCommand;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Domain\VendingMachineRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CollectTrayCommandHandlerTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testCollectingTheTrayThroughTheBusEmptiesIt(): void
    {
        $container = self::getContainer();
        $commandBus = $container->get(CommandBus::class);
        $commandBus->dispatch(new InsertCoinCommand(100));
        $commandBus->dispatch(new SelectProductCommand('WATER'));

        $commandBus->dispatch(new CollectTrayCommand());

        $tray = $container->get(VendingMachineRepository::class)->get()->tray();
        self::assertSame([], $tray->products());
        self::assertTrue($tray->coins()->isEmpty());
    }
}
