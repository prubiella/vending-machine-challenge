<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachine;

final readonly class MachineResponse
{
    /**
     * @param list<ProductResponse> $products
     */
    public function __construct(
        public array $products,
        public int $creditCents,
    ) {
    }
}
