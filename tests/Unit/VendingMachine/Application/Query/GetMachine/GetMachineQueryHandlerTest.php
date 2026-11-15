<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Query\GetMachine;

use App\Tests\Unit\VendingMachine\Application\VendingMachineRepositoryMother;
use App\VendingMachine\Application\Query\GetMachine\GetMachineQuery;
use App\VendingMachine\Application\Query\GetMachine\GetMachineQueryHandler;
use App\VendingMachine\Application\Query\GetMachine\MachineResponseFactory;
use App\VendingMachine\Application\Query\GetMachine\ProductResponse;
use App\VendingMachine\Domain\Coin\Coin;
use PHPUnit\Framework\TestCase;

final class GetMachineQueryHandlerTest extends TestCase
{
    public function testListsEachProductWithItsPriceAndWhetherItIsAvailable(): void
    {
        $response = (new GetMachineQueryHandler(VendingMachineRepositoryMother::withTheStatementProducts(), new MachineResponseFactory()))(new GetMachineQuery());

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
        $repository->get()->insertCoin(Coin::OneHundredCents);
        $repository->get()->insertCoin(Coin::TenCents);

        $response = (new GetMachineQueryHandler($repository, new MachineResponseFactory()))(new GetMachineQuery());

        self::assertSame(110, $response->creditCents);
    }
}
