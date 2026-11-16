<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ServiceMachine;

use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class ServiceMachineCommandHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
        private CashBoxCollectionLog $cashBoxCollectionLog,
    ) {
    }

    public function __invoke(ServiceMachineCommand $command): void
    {
        $machine = $this->repository->get();
        $collected = $machine->wallet()->cashBox();
        $machine->service($command->unitsBySelector, CoinCollection::fromCounts($command->coinStockByCents));
        $this->repository->save($machine);

        if (!$collected->isEmpty()) {
            $this->cashBoxCollectionLog->collected($collected);
        }
    }
}
