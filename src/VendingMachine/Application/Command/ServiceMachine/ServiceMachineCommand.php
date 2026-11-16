<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ServiceMachine;

use App\Shared\Application\Bus\Command;

final readonly class ServiceMachineCommand implements Command
{
    /**
     * @param array<int|string, int> $unitsBySelector
     * @param array<int, int>        $coinStockByCents
     */
    public function __construct(
        public array $unitsBySelector,
        public array $coinStockByCents,
    ) {
    }
}
