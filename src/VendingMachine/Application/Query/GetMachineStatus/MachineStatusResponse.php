<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachineStatus;

use App\VendingMachine\Application\Query\GetMachine\MachineResponse;

final readonly class MachineStatusResponse
{
    /**
     * @param array<int|string, int> $unitsBySelector
     * @param array<int, int>        $coinStockByCents the count of each coin by its cents, every coin included
     */
    public function __construct(
        public MachineResponse $machine,
        public array $unitsBySelector,
        public array $coinStockByCents,
    ) {
    }
}
