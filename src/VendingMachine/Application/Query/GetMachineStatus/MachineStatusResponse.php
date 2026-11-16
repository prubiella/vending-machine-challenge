<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachineStatus;

final readonly class MachineStatusResponse
{
    /**
     * @param array<int|string, int> $unitsBySelector
     * @param array<int, int>        $coinStockByCents the count of each coin by its cents, every coin included
     * @param array<int, int>        $cashBoxByCents   the same for the cash box, which holds what the tubes could not
     */
    public function __construct(
        public array $unitsBySelector,
        public array $coinStockByCents,
        public array $cashBoxByCents,
    ) {
    }
}
