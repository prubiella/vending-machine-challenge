<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ServiceMachine;

use App\VendingMachine\Domain\Coin\CoinCollection;

interface CashBoxCollectionLog
{
    public function collected(CoinCollection $coins): void;
}
