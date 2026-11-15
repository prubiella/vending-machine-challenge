<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachine;

use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class GetMachineQueryHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
    ) {
    }

    public function __invoke(GetMachineQuery $query): MachineResponse
    {
        $machine = $this->repository->get();

        return new MachineResponse(
            array_map(self::productOf(...), $machine->inventory()->slots()),
            $machine->wallet()->credit()->total()->cents(),
        );
    }

    private static function productOf(Slot $slot): ProductResponse
    {
        $product = $slot->product();

        return new ProductResponse(
            $product->selector()->value(),
            $product->name()->value(),
            $product->price()->cents(),
            !$slot->isEmpty(),
        );
    }
}
