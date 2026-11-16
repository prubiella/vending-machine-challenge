<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ServiceMachine;

use App\Shared\Application\Bus\Command;

final readonly class ServiceMachineCommand implements Command
{
    /**
     * @param array<int|string, int>                                                   $unitsBySelector
     * @param array<int, int>                                                          $coinStockByCents
     * @param list<array{selector: string, name: string, priceCents: int, units: int}> $install
     * @param list<string>                                                             $retire
     */
    public function __construct(
        public string $technicianId,
        public array $unitsBySelector,
        public array $coinStockByCents,
        public array $install = [],
        public array $retire = [],
    ) {
    }
}
