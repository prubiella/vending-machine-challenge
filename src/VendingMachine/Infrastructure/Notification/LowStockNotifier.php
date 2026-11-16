<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Notification;

/**
 * Tells whoever restocks the machine that a product is running low. The relay calls it for each
 * StockRunningLow event, at least once (ADR 20).
 */
interface LowStockNotifier
{
    public function stockRunningLow(string $selector, int $unitsLeft): void;
}
