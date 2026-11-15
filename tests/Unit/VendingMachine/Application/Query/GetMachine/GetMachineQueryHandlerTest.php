<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Query\GetMachine;

use App\Tests\Unit\VendingMachine\Application\VendingMachineRepositoryMother;
use App\VendingMachine\Application\Query\GetMachine\GetMachineQuery;
use App\VendingMachine\Application\Query\GetMachine\GetMachineQueryHandler;
use App\VendingMachine\Application\Query\GetMachine\ProductResponse;
use App\VendingMachine\Domain\Coin\Coin;
use PHPUnit\Framework\TestCase;

final class GetMachineQueryHandlerTest extends TestCase
{
    public function testListsEachProductWithItsPriceAndWhetherItIsAvailable(): void
    {
        $response = (new GetMachineQueryHandler(VendingMachineRepositoryMother::withTheStatementProducts()))(new GetMachineQuery());

        self::assertEquals(
            [
                new ProductResponse('WATER', 'Water', 65, true),
                new ProductResponse('JUICE', 'Juice', 100, true),
                new ProductResponse('SODA', 'Soda', 150, false),
            ],
            $response->products,
        );
    }

    public function testShowsTheCreditOfTheInsertedCoins(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();
        $machine = $repository->get();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->insertCoin(Coin::TenCents);
        $repository->save($machine);

        $response = (new GetMachineQueryHandler($repository))(new GetMachineQuery());

        self::assertSame(110, $response->creditCents);
    }
}
