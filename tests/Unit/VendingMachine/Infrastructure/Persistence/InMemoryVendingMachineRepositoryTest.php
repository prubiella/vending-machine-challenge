<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Persistence;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Infrastructure\Persistence\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryVendingMachineRepositoryTest extends TestCase
{
    public function testInstallsTheConfiguredProductsWithTheirUnits(): void
    {
        $machine = self::repository()->get();

        $slots = array_map(
            static fn (Slot $slot): array => [
                $slot->product()->selector()->value(),
                $slot->product()->name()->value(),
                $slot->product()->price()->cents(),
                $slot->units(),
            ],
            $machine->inventory()->slots(),
        );
        self::assertSame([['WATER', 'Water', 65, 3], ['SODA', 'Soda', 150, 0]], $slots);
    }

    public function testRefusesADefaultProductWithoutUnits(): void
    {
        $repository = new InMemoryVendingMachineRepository(
            [['selector' => 'CHIPS', 'name' => 'Chips', 'price_cents' => 120]],
            [],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The default product "CHIPS" has no units.');

        $repository->get();
    }

    public function testStocksTheConfiguredCoinsWithNoCreditAndAnEmptyTray(): void
    {
        $machine = self::repository()->get();

        self::assertTrue($machine->wallet()->stock()->equals(CoinCollection::of(Coin::FiveCents, Coin::FiveCents, Coin::TwentyFiveCents)));
        self::assertTrue($machine->wallet()->credit()->isEmpty());
        self::assertSame([], $machine->tray()->products());
        self::assertTrue($machine->tray()->coins()->isEmpty());
    }

    public function testAChangeIsNotStoredUntilTheMachineIsSaved(): void
    {
        $repository = self::repository();
        $machine = $repository->get();
        $machine->insertCoin(Coin::OneHundredCents);

        self::assertTrue($repository->get()->wallet()->credit()->isEmpty());

        $repository->save($machine);

        self::assertSame(100, $repository->get()->wallet()->credit()->total()->cents());
    }

    public function testAChangeAfterSavingIsNotStored(): void
    {
        $repository = self::repository();
        $machine = $repository->get();
        $repository->save($machine);

        $machine->insertCoin(Coin::OneHundredCents);

        self::assertTrue($repository->get()->wallet()->credit()->isEmpty());
    }

    public function testGivesBackTheMachineItSaved(): void
    {
        $repository = self::repository();
        $saved = VendingMachine::install(Product::of(Selector::fromString('JUICE'), ProductName::fromString('Juice'), Money::fromCents(100)));

        $repository->save($saved);

        self::assertEquals($saved, $repository->get());
    }

    private static function repository(): InMemoryVendingMachineRepository
    {
        return new InMemoryVendingMachineRepository(
            [
                ['selector' => 'WATER', 'name' => 'Water', 'price_cents' => 65, 'units' => 3],
                ['selector' => 'SODA', 'name' => 'Soda', 'price_cents' => 150, 'units' => 0],
            ],
            [5 => 2, 25 => 1],
        );
    }
}
