<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\SelectProduct;

use App\Shared\Application\Bus\Command;

final readonly class SelectProductCommand implements Command
{
    public function __construct(
        public string $selector,
    ) {
    }
}
