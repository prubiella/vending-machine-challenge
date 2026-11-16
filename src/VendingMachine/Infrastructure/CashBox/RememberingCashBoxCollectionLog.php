<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\CashBox;

use App\VendingMachine\Application\Command\ServiceMachine\CashBoxCollectionLog;
use App\VendingMachine\Domain\Coin\CoinCollection;

// Lets the console say what the handler collected, as the handler saw it after its last attempt:
// commands return nothing, so a query made before the dispatch could be stale.
final class RememberingCashBoxCollectionLog implements CashBoxCollectionLog
{
    private ?CoinCollection $lastCollected = null;

    public function __construct(private readonly CashBoxCollectionLog $log)
    {
    }

    public function collected(CoinCollection $coins): void
    {
        $this->lastCollected = $coins;
        $this->log->collected($coins);
    }

    public function takeLastCollected(): CoinCollection
    {
        $collected = $this->lastCollected ?? CoinCollection::empty();
        $this->lastCollected = null;

        return $collected;
    }
}
