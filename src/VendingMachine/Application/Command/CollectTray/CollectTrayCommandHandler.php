<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\CollectTray;

use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class CollectTrayCommandHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
    ) {
    }

    public function __invoke(CollectTrayCommand $command): void
    {
        $machine = $this->repository->get();
        $machine->collectTray();
        $this->repository->save($machine);
    }
}
