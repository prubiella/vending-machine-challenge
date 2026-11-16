<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ServiceMachine;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\CatalogChange;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\TechnicianId;
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
        $machine->service(
            TechnicianId::fromString($command->technicianId),
            $command->unitsBySelector,
            CoinCollection::fromCounts($command->coinStockByCents),
            self::catalogChange($command),
        );
        $this->repository->save($machine);

        if (!$collected->isEmpty()) {
            $this->cashBoxCollectionLog->collected($collected);
        }
    }

    private static function catalogChange(ServiceMachineCommand $command): CatalogChange
    {
        return CatalogChange::of(
            array_map(
                static fn (array $product): Slot => Slot::installed(
                    Product::of(Selector::fromString($product['selector']), ProductName::fromString($product['name']), Money::fromCents($product['priceCents'])),
                    $product['units'],
                ),
                $command->install,
            ),
            array_map(Selector::fromString(...), $command->retire),
        );
    }
}
