<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Application\Query\GetMachine;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Query\GetMachine\GetMachineQuery;
use App\VendingMachine\Application\Query\GetMachine\MachineResponse;
use App\VendingMachine\Application\Query\GetMachine\ProductResponse;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GetMachineQueryHandlerTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testTheBusAnswersWithTheProductsAndTheCredit(): void
    {
        $container = self::getContainer();
        $container->get(CommandBus::class)->dispatch(new InsertCoinCommand(25));

        $response = $container->get(QueryBus::class)->query(new GetMachineQuery());

        $products = [
            new ProductResponse('WATER', 'Water', 65, true),
            new ProductResponse('JUICE', 'Juice', 100, true),
            new ProductResponse('SODA', 'Soda', 150, true),
        ];
        self::assertEquals(new MachineResponse($products, 25), $response);
    }
}
