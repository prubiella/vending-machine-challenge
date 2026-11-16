<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Query\GetMachineStatus;

use App\Tests\Unit\VendingMachine\Application\VendingMachineRepositoryMother;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQueryHandler;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use PHPUnit\Framework\TestCase;

final class GetMachineStatusQueryHandlerTest extends TestCase
{
    public function testShowsTheUnitsOfEachProductAndTheCountOfEveryCoin(): void
    {
        $response = (new GetMachineStatusQueryHandler(VendingMachineRepositoryMother::withTheStatementProducts()))(new GetMachineStatusQuery());

        self::assertEquals(
            new MachineStatusResponse(['WATER' => 5, 'JUICE' => 5, 'SODA' => 0], [5 => 2, 10 => 2, 25 => 2, 100 => 0]),
            $response,
        );
    }
}
