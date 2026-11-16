<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Infrastructure\Persistence\DefaultVendingMachineFactory;
use PHPUnit\Framework\TestCase;

final class InMemoryVendingMachineRepositoryTest extends TestCase
{
    private const string MACHINE_ID = '01a1e2f0-aa80-70ea-832f-6110cbf86e31';

    public function testGivesTheDefaultMachineUntilOneIsSaved(): void
    {
        $defaultMachineFactory = self::defaultMachineFactory();

        self::assertEquals($defaultMachineFactory->create(), new InMemoryVendingMachineRepository($defaultMachineFactory)->get());
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
        $saved = VendingMachine::install(MachineId::fromString(self::MACHINE_ID), Product::of(Selector::fromString('JUICE'), ProductName::fromString('Juice'), Money::fromCents(100)));

        $repository->save($saved);

        self::assertEquals($saved, $repository->get());
    }

    private static function repository(): InMemoryVendingMachineRepository
    {
        return new InMemoryVendingMachineRepository(self::defaultMachineFactory());
    }

    private static function defaultMachineFactory(): DefaultVendingMachineFactory
    {
        return new DefaultVendingMachineFactory(
            MachineId::fromString(self::MACHINE_ID),
            [['selector' => 'WATER', 'name' => 'Water', 'price_cents' => 65, 'units' => 3]],
            [5 => 2, 25 => 1],
        );
    }
}
