<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\InsertCoin;

use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class InsertCoinCommandHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
    ) {
    }

    public function __invoke(InsertCoinCommand $command): void
    {
        $machine = $this->repository->get();
        $machine->insertCoin(Coin::fromCents($command->cents));
        $this->repository->save($machine);
    }
}
