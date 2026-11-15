<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachine;

final readonly class ProductResponse
{
    public function __construct(
        public string $selector,
        public string $name,
        public int $priceCents,
        public bool $available,
    ) {
    }
}
