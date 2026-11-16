<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

use App\VendingMachine\Domain\Coin\CoinCollection;

final readonly class CreditReturned implements VendingMachineEvent
{
    public function __construct(
        private CoinCollection $coins,
    ) {
    }

    public function coins(): CoinCollection
    {
        return $this->coins;
    }
}
