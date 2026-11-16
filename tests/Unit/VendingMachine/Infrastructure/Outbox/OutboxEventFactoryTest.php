<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Outbox;

use App\Shared\Application\Uuid\UuidGenerator;
use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\CatalogChange;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\TechnicianId;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Infrastructure\Outbox\OutboxEvent;
use App\VendingMachine\Infrastructure\Outbox\OutboxEventFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class OutboxEventFactoryTest extends TestCase
{
    private const string TECHNICIAN_ID = '0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61';
    private const string NOW = '2026-11-16 18:30:00.123456';

    public function testGivesEachEventOfTheMachineAnIdATimeAndItsPayload(): void
    {
        $machine = self::machine();
        $machine->service(TechnicianId::fromString(self::TECHNICIAN_ID), ['WATER' => 3], CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents));
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents));
        $machine->insertCoin(Coin::FiveCents);
        $machine->returnCoins();
        $machine->service(TechnicianId::fromString(self::TECHNICIAN_ID), [], CoinCollection::of(Coin::TwentyFiveCents));

        $events = self::factory()->releasedFrom($machine);

        self::assertEquals([
            new OutboxEvent('00000000-0000-7000-8000-000000000001', 'machine_serviced', new \DateTimeImmutable(self::NOW), [
                'technician_id' => self::TECHNICIAN_ID,
                'units' => ['WATER' => 3],
                'coins' => [5 => 0, 10 => 1, 25 => 1, 100 => 0],
                'collected_from_cash_box' => [5 => 0, 10 => 0, 25 => 0, 100 => 0],
            ]),
            new OutboxEvent('00000000-0000-7000-8000-000000000002', 'product_sold', new \DateTimeImmutable(self::NOW), [
                'selector' => 'WATER',
                'name' => 'Water',
                'price_cents' => 65,
                'change' => [5 => 0, 10 => 1, 25 => 1, 100 => 0],
            ]),
            new OutboxEvent('00000000-0000-7000-8000-000000000003', 'credit_returned', new \DateTimeImmutable(self::NOW), [
                'coins' => [5 => 1, 10 => 0, 25 => 0, 100 => 0],
            ]),
            new OutboxEvent('00000000-0000-7000-8000-000000000004', 'machine_serviced', new \DateTimeImmutable(self::NOW), [
                'technician_id' => self::TECHNICIAN_ID,
                'units' => [],
                'coins' => [5 => 0, 10 => 0, 25 => 1, 100 => 0],
                'collected_from_cash_box' => [5 => 0, 10 => 0, 25 => 0, 100 => 1],
            ]),
        ], $events);
    }

    public function testReleasesTheEventsSoTheyAreSavedOnce(): void
    {
        $machine = self::machine();
        $machine->insertCoin(Coin::FiveCents);
        $machine->returnCoins();
        $factory = self::factory();

        $factory->releasedFrom($machine);

        self::assertSame([], $factory->releasedFrom($machine));
    }

    public function testDescribesALowStockWithTheSelectorAndTheUnitsLeft(): void
    {
        $machine = self::machine();
        $machine->service(TechnicianId::fromString(self::TECHNICIAN_ID), ['WATER' => 6], CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents));
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents));

        $events = self::factory()->releasedFrom($machine);

        self::assertSame(OutboxEventFactory::STOCK_RUNNING_LOW, $events[2]->type);
        self::assertSame(['selector' => 'WATER', 'units_left' => 5], $events[2]->payload);
    }

    public function testDescribesWhatAServiceRetiredAndInstalledWithWhoDidIt(): void
    {
        $machine = self::machine();
        $machine->service(TechnicianId::fromString(self::TECHNICIAN_ID), ['WATER' => 4], CoinCollection::empty());
        $machine->service(
            TechnicianId::fromString(self::TECHNICIAN_ID),
            [],
            CoinCollection::empty(),
            CatalogChange::of(
                [Slot::installed(Product::of(Selector::fromString('TEA'), ProductName::fromString('Tea'), Money::fromCents(120)), 3)],
                [Selector::fromString('WATER')],
            ),
        );

        $events = self::factory()->releasedFrom($machine);

        self::assertSame('product_retired', $events[2]->type);
        self::assertSame([
            'technician_id' => self::TECHNICIAN_ID,
            'selector' => 'WATER',
            'name' => 'Water',
            'price_cents' => 65,
            'units_removed' => 4,
        ], $events[2]->payload);
        self::assertSame('product_installed', $events[3]->type);
        self::assertSame([
            'technician_id' => self::TECHNICIAN_ID,
            'selector' => 'TEA',
            'name' => 'Tea',
            'price_cents' => 120,
            'units' => 3,
        ], $events[3]->payload);
    }

    private static function factory(): OutboxEventFactory
    {
        $sequence = new class implements UuidGenerator {
            private int $next = 1;

            public function generate(): string
            {
                return \sprintf('00000000-0000-7000-8000-%012d', $this->next++);
            }
        };

        return new OutboxEventFactory($sequence, new MockClock(new \DateTimeImmutable(self::NOW)));
    }

    private static function machine(): VendingMachine
    {
        return VendingMachine::install(
            MachineId::fromString('01a1e2f0-aa80-70ea-832f-6110cbf86e31'),
            Product::of(Selector::fromString('WATER'), ProductName::fromString('Water'), Money::fromCents(65)),
        );
    }
}
