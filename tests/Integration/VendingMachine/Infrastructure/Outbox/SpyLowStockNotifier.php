<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Outbox;

use App\VendingMachine\Infrastructure\Notification\LowStockNotifier;

final class SpyLowStockNotifier implements LowStockNotifier
{
    /** @var list<array{string, int}> */
    public array $notified = [];

    public function stockRunningLow(string $selector, int $unitsLeft): void
    {
        $this->notified[] = [$selector, $unitsLeft];
    }
}
