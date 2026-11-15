<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachineStatus;

use App\VendingMachine\Application\Query\GetMachine\MachineResponseFactory;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class GetMachineStatusQueryHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
        private MachineResponseFactory $responses,
    ) {
    }

    public function __invoke(GetMachineStatusQuery $query): MachineStatusResponse
    {
        $machine = $this->repository->get();

        return new MachineStatusResponse(
            $this->responses->fromMachine($machine),
            self::unitsBySelector($machine->inventory()),
            self::countsByCents($machine->wallet()->stock()),
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

    /**
     * @return array<int, int>
     */
    private static function countsByCents(CoinCollection $coins): array
    {
        $counts = [];
        foreach (Coin::cases() as $coin) {
            $counts[$coin->value] = $coins->countOf($coin);
        }

        return $counts;
    }
}
