<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Persistence;

use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Infrastructure\Persistence\DefaultVendingMachineFactory;
use PHPUnit\Framework\TestCase;

final class DefaultVendingMachineFactoryTest extends TestCase
{
    private const string MACHINE_ID = '01a1e2f0-aa80-70ea-832f-6110cbf86e31';

    public function testInstallsTheMachineUnderTheConfiguredId(): void
    {
        self::assertTrue(self::factory()->create()->id()->equals(MachineId::fromString(self::MACHINE_ID)));
    }

    public function testInstallsTheConfiguredProductsWithTheirUnits(): void
    {
        $machine = self::factory()->create();

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
        $factory = new DefaultVendingMachineFactory(
            MachineId::fromString(self::MACHINE_ID),
            [['selector' => 'CHIPS', 'name' => 'Chips', 'price_cents' => 120]],
            [],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The default product "CHIPS" has no units.');

        $factory->create();
    }

    public function testStocksTheConfiguredCoinsWithNoCreditAndAnEmptyTray(): void
    {
        $machine = self::factory()->create();

        self::assertTrue($machine->wallet()->stock()->equals(CoinCollection::of(Coin::FiveCents, Coin::FiveCents, Coin::TwentyFiveCents)));
        self::assertTrue($machine->wallet()->credit()->isEmpty());
        self::assertSame([], $machine->tray()->products());
        self::assertTrue($machine->tray()->coins()->isEmpty());
    }

    public function testEveryCallGivesANewMachine(): void
    {
        $factory = self::factory();

        self::assertNotSame($factory->create(), $factory->create());
    }

    private static function factory(): DefaultVendingMachineFactory
    {
        return new DefaultVendingMachineFactory(
            MachineId::fromString(self::MACHINE_ID),
            [
                ['selector' => 'WATER', 'name' => 'Water', 'price_cents' => 65, 'units' => 3],
                ['selector' => 'SODA', 'name' => 'Soda', 'price_cents' => 150, 'units' => 0],
            ],
            [5 => 2, 25 => 1],
        );
    }
}
