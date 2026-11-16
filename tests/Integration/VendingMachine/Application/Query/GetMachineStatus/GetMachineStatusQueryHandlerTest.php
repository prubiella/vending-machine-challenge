<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Application\Query\GetMachineStatus;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GetMachineStatusQueryHandlerTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testTheBusAnswersWithTheUnitsTheCoinStockAndAnEmptyCashBox(): void
    {
        $container = self::getContainer();
        $container->get(CommandBus::class)->dispatch(new ServiceMachineCommand(
            '0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61',
            ['WATER' => 1, 'JUICE' => 2, 'SODA' => 3],
            [5 => 4, 10 => 5, 25 => 6, 100 => 7],
        ));

        $response = $container->get(QueryBus::class)->query(new GetMachineStatusQuery());

        self::assertInstanceOf(MachineStatusResponse::class, $response);
        self::assertSame(['WATER' => 1, 'JUICE' => 2, 'SODA' => 3], $response->unitsBySelector);
        self::assertSame([5 => 4, 10 => 5, 25 => 6, 100 => 7], $response->coinStockByCents);
        self::assertSame([5 => 0, 10 => 0, 25 => 0, 100 => 0], $response->cashBoxByCents);
    }

    public function testTheBusAnswersWithTheCoinsOfTheCashBox(): void
    {
        $container = self::getContainer();
        $commandBus = $container->get(CommandBus::class);
        $commandBus->dispatch(new ServiceMachineCommand('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61', ['WATER' => 1], [5 => 4, 10 => 5, 25 => 6]));
        $commandBus->dispatch(new InsertCoinCommand(100));
        $commandBus->dispatch(new SelectProductCommand('WATER'));

        $response = $container->get(QueryBus::class)->query(new GetMachineStatusQuery());

        self::assertInstanceOf(MachineStatusResponse::class, $response);
        self::assertSame([5 => 0, 10 => 0, 25 => 0, 100 => 1], $response->cashBoxByCents);
        self::assertSame([5 => 4, 10 => 4, 25 => 5, 100 => 0], $response->coinStockByCents);
    }
}
