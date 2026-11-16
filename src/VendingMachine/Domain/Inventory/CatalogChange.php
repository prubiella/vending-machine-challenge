<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\VendingMachine\Domain\Product\Selector;

/**
 * The products a service installs, each with the units it starts with, and the ones it retires
 * (ADR 22). Installing a selector the machine already has redefines that product.
 */
final readonly class CatalogChange
{
    /**
     * @param list<Slot>     $installs
     * @param list<Selector> $retires
     */
    private function __construct(
        private array $installs,
        private array $retires,
    ) {
    }

    public static function none(): self
    {
        return new self([], []);
    }

    /**
     * @param list<Slot>     $installs
     * @param list<Selector> $retires
     */
    public static function of(array $installs, array $retires): self
    {
        $installed = [];
        foreach ($installs as $slot) {
            $selector = $slot->product()->selector();
            if (isset($installed[$selector->value()])) {
                throw ContradictoryCatalogChangeException::installedTwice($selector);
            }
            $installed[$selector->value()] = true;
        }

        $retired = [];
        foreach ($retires as $selector) {
            if (isset($retired[$selector->value()])) {
                throw ContradictoryCatalogChangeException::retiredTwice($selector);
            }
            if (isset($installed[$selector->value()])) {
                throw ContradictoryCatalogChangeException::installedAndRetired($selector);
            }
            $retired[$selector->value()] = true;
        }

        return new self($installs, $retires);
    }

    /**
     * @return list<Slot>
     */
    public function installs(): array
    {
        return $this->installs;
    }

    /**
     * @return list<Selector>
     */
    public function retires(): array
    {
        return $this->retires;
    }

    /**
     * The units a service sets apart from the catalog change belong to the products it leaves in
     * place: an installed product comes with its own units, and a retired one has none left to set.
     *
     * @param array<int|string, int> $unitsBySelector
     */
    public function refuseUnitsGivenTo(array $unitsBySelector): void
    {
        foreach ($this->installs as $slot) {
            $selector = $slot->product()->selector();
            if (\array_key_exists($selector->value(), $unitsBySelector)) {
                throw ContradictoryCatalogChangeException::restockedAndInstalled($selector);
            }
        }
        foreach ($this->retires as $selector) {
            if (\array_key_exists($selector->value(), $unitsBySelector)) {
                throw ContradictoryCatalogChangeException::restockedAndRetired($selector);
            }
        }
    }
}
