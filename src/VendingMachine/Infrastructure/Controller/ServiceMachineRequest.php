<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

final readonly class ServiceMachineRequest
{
    /**
     * @param array<int|string, int> $units the units of each product given, by its selector
     * @param array<int|string, int> $coins the count of each coin, by its notation ("0.25"); JSON
     *                                      keys such as "1" arrive as integers
     */
    public function __construct(
        public array $units,
        public array $coins,
    ) {
    }
}
