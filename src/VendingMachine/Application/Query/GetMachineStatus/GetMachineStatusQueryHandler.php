<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachineStatus;

use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class GetMachineStatusQueryHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
    ) {
    }

    public function __invoke(GetMachineStatusQuery $query): MachineStatusResponse
    {
        $machine = $this->repository->get();

        return new MachineStatusResponse(
            self::unitsBySelector($machine->inventory()),
            $machine->wallet()->stock()->countsByCents(),
        );
    }

    /**
     * @return array<int|string, int>
     */
    private static function unitsBySelector(Inventory $inventory): array
    {
        $units = [];
        foreach ($inventory->slots() as $slot) {
            $units[$slot->product()->selector()->value()] = $slot->units();
        }

        return $units;
    }
}
