<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Application\Command\ServiceMachine;

use App\Shared\Application\Bus\CommandBus;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\VendingMachineRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ServiceMachineCommandHandlerTest extends KernelTestCase
{
    public function testServicingThroughTheBusRestocksTheProductsAndTheCoins(): void
    {
        $container = self::getContainer();

        $container->get(CommandBus::class)->dispatch(new ServiceMachineCommand(['JUICE' => 2], [25 => 2]));

        $machine = $container->get(VendingMachineRepository::class)->get();
        self::assertSame(2, $machine->inventory()->slotFor(Selector::fromString('JUICE'))->units());
        self::assertTrue($machine->wallet()->stock()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TwentyFiveCents)));
    }
}
