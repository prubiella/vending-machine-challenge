<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

final readonly class InsertCoinRequest
{
    public function __construct(
        public string $coin,
    ) {
    }
}
