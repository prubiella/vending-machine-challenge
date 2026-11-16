<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\TechnicianId;

final readonly class MachineServiced implements VendingMachineEvent
{
    /**
     * @param array<int|string, int> $unitsBySelector
     */
    public function __construct(
        private TechnicianId $technicianId,
        private array $unitsBySelector,
        private CoinCollection $coins,
        private CoinCollection $collectedFromCashBox,
    ) {
    }

    public function technicianId(): TechnicianId
    {
        return $this->technicianId;
    }

    /**
     * @return array<int|string, int>
     */
    public function unitsBySelector(): array
    {
        return $this->unitsBySelector;
    }

    public function coins(): CoinCollection
    {
        return $this->coins;
    }

    public function collectedFromCashBox(): CoinCollection
    {
        return $this->collectedFromCashBox;
    }
}
