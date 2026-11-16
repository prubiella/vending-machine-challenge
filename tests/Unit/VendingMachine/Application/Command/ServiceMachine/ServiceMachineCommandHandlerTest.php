<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Command\ServiceMachine;

use App\Tests\Unit\VendingMachine\Application\VendingMachineRepositoryMother;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommandHandler;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Event\MachineServiced;
use App\VendingMachine\Domain\Event\ProductInstalled;
use App\VendingMachine\Domain\Event\ProductRetired;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\VendingMachine;
use PHPUnit\Framework\TestCase;

final class ServiceMachineCommandHandlerTest extends TestCase
{
    private const string TECHNICIAN_ID = '0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61';

    public function testSetsTheUnitsOfEachProductAndTheCoinStock(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();

        (new ServiceMachineCommandHandler($repository, new RecordingCashBoxCollectionLog()))(new ServiceMachineCommand(
            self::TECHNICIAN_ID,
            ['WATER' => 1, 'SODA' => 7],
            [5 => 0, 10 => 3, 100 => 1],
        ));

        $machine = $repository->get();
        self::assertSame(1, self::unitsOf($machine, 'WATER'));
        self::assertSame(5, self::unitsOf($machine, 'JUICE'));
        self::assertSame(7, self::unitsOf($machine, 'SODA'));
        $stock = CoinCollection::of(Coin::TenCents, Coin::TenCents, Coin::TenCents, Coin::OneHundredCents);
        self::assertTrue($machine->wallet()->stock()->equals($stock));
    }

    public function testReportsWhatTheCashBoxHeldAndEmptiesIt(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();
        $machine = $repository->get();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents));
        $repository->save($machine);
        $log = new RecordingCashBoxCollectionLog();

        (new ServiceMachineCommandHandler($repository, $log))(new ServiceMachineCommand(self::TECHNICIAN_ID, ['WATER' => 1], [5 => 1]));

        self::assertCount(1, $log->collected);
        self::assertTrue($log->collected[0]->equals(CoinCollection::of(Coin::OneHundredCents)));
        self::assertTrue($repository->get()->wallet()->cashBox()->isEmpty());
    }

    public function testReportsNothingWhenTheCashBoxWasEmpty(): void
    {
        $log = new RecordingCashBoxCollectionLog();

        (new ServiceMachineCommandHandler(VendingMachineRepositoryMother::withTheStatementProducts(), $log))(
            new ServiceMachineCommand(self::TECHNICIAN_ID, ['WATER' => 1], [5 => 1]),
        );

        self::assertSame([], $log->collected);
    }

    public function testRecordsWhichTechnicianServicedTheMachine(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();

        (new ServiceMachineCommandHandler($repository, new RecordingCashBoxCollectionLog()))(new ServiceMachineCommand(self::TECHNICIAN_ID, ['WATER' => 1], [10 => 3]));

        $events = $repository->savedEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MachineServiced::class, $events[0]);
        self::assertSame(self::TECHNICIAN_ID, $events[0]->technicianId()->value());
    }

    public function testInstallsAndRetiresTheProductsTheCommandNames(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();

        (new ServiceMachineCommandHandler($repository, new RecordingCashBoxCollectionLog()))(new ServiceMachineCommand(
            self::TECHNICIAN_ID,
            ['SODA' => 2],
            [],
            [['selector' => 'TEA', 'name' => 'Green tea', 'priceCents' => 120, 'units' => 6]],
            ['WATER'],
        ));

        $slots = array_map(
            static fn (Slot $slot): array => [$slot->product()->selector()->value(), $slot->product()->name()->value(), $slot->product()->price()->cents(), $slot->units()],
            $repository->get()->inventory()->slots(),
        );
        self::assertSame([['JUICE', 'Juice', 100, 5], ['SODA', 'Soda', 150, 2], ['TEA', 'Green tea', 120, 6]], $slots);
        self::assertSame(
            [MachineServiced::class, ProductRetired::class, ProductInstalled::class],
            array_map(static fn (object $event): string => $event::class, $repository->savedEvents()),
        );
    }

    private static function unitsOf(VendingMachine $machine, string $selector): int
    {
        return $machine->inventory()->slotFor(Selector::fromString($selector))->units();
    }
}
