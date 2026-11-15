<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ServiceMachine;

use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class ServiceMachineCommandHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
    ) {
    }

    public function __invoke(ServiceMachineCommand $command): void
    {
        $machine = $this->repository->get();
        $machine->service($command->unitsBySelector, CoinCollection::fromCounts($command->coinCountsByCents));
        $this->repository->save($machine);
    }
}
