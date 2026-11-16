<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

use App\VendingMachine\Domain\Product\Selector;

/**
 * A sale left a slot at or under Slot::LOW_STOCK_THRESHOLD, coming from above it: recorded once
 * per crossing, not on every later sale.
 */
final readonly class StockRunningLow implements VendingMachineEvent
{
    public function __construct(
        private Selector $selector,
        private int $unitsLeft,
    ) {
    }

    public function selector(): Selector
    {
        return $this->selector;
    }

    public function unitsLeft(): int
    {
        return $this->unitsLeft;
    }
}
