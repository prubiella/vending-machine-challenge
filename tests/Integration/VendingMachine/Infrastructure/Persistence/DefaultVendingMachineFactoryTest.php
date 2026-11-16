<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence;

use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Infrastructure\Persistence\DefaultVendingMachineFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DefaultVendingMachineFactoryTest extends KernelTestCase
{
    public function testTheDefaultMachineSellsTheProductsOfTheStatement(): void
    {
        $products = array_map(
            static fn (Slot $slot): array => [
                $slot->product()->selector()->value(),
                $slot->product()->name()->value(),
                $slot->product()->price()->cents(),
            ],
            self::defaultMachine()->inventory()->slots(),
        );

        self::assertSame([['WATER', 'Water', 65], ['JUICE', 'Juice', 100], ['SODA', 'Soda', 150]], $products);
    }

    public function testTheDefaultMachineHasTheChangeOfTheStatementExample(): void
    {
        $machine = self::defaultMachine();

        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents));

        self::assertTrue($machine->tray()->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents)));
    }

    private static function defaultMachine(): VendingMachine
    {
        return self::getContainer()->get(DefaultVendingMachineFactory::class)->create();
    }
}
