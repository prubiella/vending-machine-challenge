<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Command\ServiceMachine;

use App\Tests\Unit\VendingMachine\Application\VendingMachineRepositoryMother;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommandHandler;
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

        (new ServiceMachineCommandHandler($repository))(new ServiceMachineCommand(
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

    private static function unitsOf(VendingMachine $machine, string $selector): int
    {
        return $machine->inventory()->slotFor(Selector::fromString($selector))->units();
    }
}
