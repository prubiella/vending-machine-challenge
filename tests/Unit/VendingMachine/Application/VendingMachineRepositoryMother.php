<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application;

use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Infrastructure\Persistence\DefaultVendingMachineFactory;

final class VendingMachineRepositoryMother
{
    public static function withTheStatementProducts(): InMemoryVendingMachineRepository
    {
        return new InMemoryVendingMachineRepository(new DefaultVendingMachineFactory(
            MachineId::fromString('01a1e2f0-aa80-70ea-832f-6110cbf86e31'),
            [
                ['selector' => 'WATER', 'name' => 'Water', 'price_cents' => 65, 'units' => 5],
                ['selector' => 'JUICE', 'name' => 'Juice', 'price_cents' => 100, 'units' => 5],
                ['selector' => 'SODA', 'name' => 'Soda', 'price_cents' => 150, 'units' => 0],
            ],
            [5 => 2, 10 => 2, 25 => 2],
        ));
    }
}
