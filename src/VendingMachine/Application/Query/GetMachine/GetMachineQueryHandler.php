<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachine;

use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class GetMachineQueryHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
        private MachineResponseFactory $machineResponseFactory,
    ) {
    }

    public function __invoke(GetMachineQuery $query): MachineResponse
    {
        return $this->machineResponseFactory->fromMachine($this->repository->get());
    }
}
