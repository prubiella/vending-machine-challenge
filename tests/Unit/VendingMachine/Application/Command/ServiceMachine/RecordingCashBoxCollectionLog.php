<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Command\ServiceMachine;

use App\VendingMachine\Application\Command\ServiceMachine\CashBoxCollectionLog;
use App\VendingMachine\Domain\Coin\CoinCollection;

final class RecordingCashBoxCollectionLog implements CashBoxCollectionLog
{
    /** @var list<CoinCollection> */
    public array $collected = [];

    public function collected(CoinCollection $coins): void
    {
        $this->collected[] = $coins;
    }
}
