<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\SelectProduct;

use App\VendingMachine\Domain\Change\ChangeMaker;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class SelectProductCommandHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
        private ChangeMaker $changeMaker,
    ) {
    }

    public function __invoke(SelectProductCommand $command): void
    {
        $machine = $this->repository->get();
        $machine->select(Selector::fromString($command->selector), $this->changeMaker);
        $this->repository->save($machine);
    }
}
