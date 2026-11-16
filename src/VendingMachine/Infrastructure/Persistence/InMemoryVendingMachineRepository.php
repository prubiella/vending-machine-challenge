<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\VendingMachineRepository;

final class InMemoryVendingMachineRepository implements VendingMachineRepository
{
    private ?VendingMachine $machine = null;

    /**
     * @param list<array{selector: string, name: string, price_cents: int, units?: int}> $products
     * @param array<int, int>                                                            $coinStockByCents
     */
    public function __construct(
        private readonly array $products,
        private readonly array $coinStockByCents,
    ) {
    }

    // Copies in and out, so a change reaches the store only through save(), as with a database.
    // The parts of the machine are immutable, so a shallow clone is enough.
    public function get(): VendingMachine
    {
        $this->machine ??= $this->installDefaultMachine();

        return clone $this->machine;
    }

    public function save(VendingMachine $machine): void
    {
        $this->machine = clone $machine;
    }

    private function installDefaultMachine(): VendingMachine
    {
        $machine = VendingMachine::install(...$this->defaultProducts());
        $machine->service($this->defaultUnitsBySelector(), CoinCollection::fromCounts($this->coinStockByCents));

        return $machine;
    }

    /**
     * @return list<Product>
     */
    private function defaultProducts(): array
    {
        return array_map(
            static fn (array $product): Product => Product::of(
                Selector::fromString($product['selector']),
                ProductName::fromString($product['name']),
                Money::fromCents($product['price_cents']),
            ),
            $this->products,
        );
    }

    /**
     * @return array<string, int>
     */
    private function defaultUnitsBySelector(): array
    {
        $unitsBySelector = [];
        foreach ($this->products as $product) {
            $unitsBySelector[$product['selector']] = $product['units']
                ?? throw new \InvalidArgumentException(\sprintf('The default product "%s" has no units.', $product['selector']));
        }

        return $unitsBySelector;
    }
}
