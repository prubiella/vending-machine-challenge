<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Application\Query\GetMachineStatus;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GetMachineStatusQueryHandlerTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testTheBusAnswersWithTheUnitsAndTheCoinStock(): void
    {
        $container = self::getContainer();
        $container->get(CommandBus::class)->dispatch(new ServiceMachineCommand(
            'a-technician-id',
            ['WATER' => 1, 'JUICE' => 2, 'SODA' => 3],
            [5 => 4, 10 => 5, 25 => 6, 100 => 7],
        ));

        $response = $container->get(QueryBus::class)->query(new GetMachineStatusQuery());

        self::assertInstanceOf(MachineStatusResponse::class, $response);
        self::assertSame(['WATER' => 1, 'JUICE' => 2, 'SODA' => 3], $response->unitsBySelector);
        self::assertSame([5 => 4, 10 => 5, 25 => 6, 100 => 7], $response->coinStockByCents);
    }
}
