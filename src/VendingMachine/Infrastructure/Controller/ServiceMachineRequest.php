<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

final readonly class ServiceMachineRequest
{
    /**
     * @param array<int|string, mixed> $units   the units of each product given, by its selector
     * @param array<int|string, mixed> $coins   the count of each coin, by its notation ("0.25"); JSON
     *                                          keys such as "1" arrive as integers
     * @param array<int|string, mixed> $install the products to install, each with its selector, name,
     *                                          priceCents and units
     * @param array<int|string, mixed> $retire  the selectors of the products to retire
     */
    public function __construct(
        public array $units,
        public array $coins,
        public array $install = [],
        public array $retire = [],
    ) {
    }
}
