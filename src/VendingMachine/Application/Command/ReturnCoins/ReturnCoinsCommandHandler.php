<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ReturnCoins;

use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class ReturnCoinsCommandHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
    ) {
    }

    public function __invoke(ReturnCoinsCommand $command): void
    {
        $machine = $this->repository->get();
        $machine->returnCoins();
        $this->repository->save($machine);
    }
}
