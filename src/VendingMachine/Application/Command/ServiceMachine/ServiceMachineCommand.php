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
        // No handler reads it: the command bus log records who serviced the machine, the audit
        // trail until a MachineServiced domain event exists.
        public string $technicianId,
        public array $unitsBySelector,
        public array $coinStockByCents,
    ) {
    }
}
