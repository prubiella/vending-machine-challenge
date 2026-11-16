<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Command\ServiceMachine;

use App\Tests\Unit\VendingMachine\Application\VendingMachineRepositoryMother;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommandHandler;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\VendingMachine;
use PHPUnit\Framework\TestCase;

final class ServiceMachineCommandHandlerTest extends TestCase
{
    public function testSetsTheUnitsOfEachProductAndTheCoinStock(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();

        (new ServiceMachineCommandHandler($repository, new RecordingCashBoxCollectionLog()))(new ServiceMachineCommand(
            'a-technician-id',
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

        (new ServiceMachineCommandHandler($repository, $log))(new ServiceMachineCommand('a-technician-id', ['WATER' => 1], [5 => 1]));

        self::assertCount(1, $log->collected);
        self::assertTrue($log->collected[0]->equals(CoinCollection::of(Coin::OneHundredCents)));
        self::assertTrue($repository->get()->wallet()->cashBox()->isEmpty());
    }

    public function testReportsNothingWhenTheCashBoxWasEmpty(): void
    {
        $log = new RecordingCashBoxCollectionLog();

        (new ServiceMachineCommandHandler(VendingMachineRepositoryMother::withTheStatementProducts(), $log))(
            new ServiceMachineCommand('a-technician-id', ['WATER' => 1], [5 => 1]),
        );

        self::assertSame([], $log->collected);
    }

    private static function unitsOf(VendingMachine $machine, string $selector): int
    {
        return $machine->inventory()->slotFor(Selector::fromString($selector))->units();
    }
}
