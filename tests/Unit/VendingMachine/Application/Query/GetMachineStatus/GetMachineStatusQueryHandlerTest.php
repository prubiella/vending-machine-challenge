<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Query\GetMachineStatus;

use App\Tests\Unit\VendingMachine\Application\VendingMachineRepositoryMother;
use App\VendingMachine\Application\Query\GetMachine\MachineResponse;
use App\VendingMachine\Application\Query\GetMachine\MachineResponseFactory;
use App\VendingMachine\Application\Query\GetMachine\ProductResponse;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQueryHandler;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use App\VendingMachine\Domain\Coin\Coin;
use PHPUnit\Framework\TestCase;

final class GetMachineStatusQueryHandlerTest extends TestCase
{
    public function testShowsWhatTheCustomerSeesWithTheUnitsOfEachProductAndTheCoinStock(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();
        $repository->get()->insertCoin(Coin::OneHundredCents);

        $response = (new GetMachineStatusQueryHandler($repository, new MachineResponseFactory()))(new GetMachineStatusQuery());

        $machine = new MachineResponse(
            [
                new ProductResponse('WATER', 'Water', 65, true),
                new ProductResponse('JUICE', 'Juice', 100, true),
                new ProductResponse('SODA', 'Soda', 150, false),
            ],
            100,
        );
        self::assertEquals(
            new MachineStatusResponse($machine, ['WATER' => 5, 'JUICE' => 5, 'SODA' => 0], [5 => 2, 10 => 2, 25 => 2, 100 => 0]),
            $response,
        );
    }
}
