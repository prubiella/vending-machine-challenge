<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\VendingMachine\Domain\Product\Selector;

final readonly class Inventory
{
    public const int CAPACITY = 40;

    /**
     * @param array<string, Slot> $slotsBySelector
     */
    private function __construct(private array $slotsBySelector)
    {
    }

    public static function of(Slot ...$slots): self
    {
        $slotsBySelector = [];
        foreach ($slots as $slot) {
            $selector = $slot->product()->selector();
            if (isset($slotsBySelector[$selector->value()])) {
                throw DuplicateSelectorException::forSelector($selector);
            }
            $slotsBySelector[$selector->value()] = $slot;
        }

        return new self($slotsBySelector);
    }

    /**
     * The slots of a machine being installed, bounded by its capacity. A stored inventory is built
     * with of(), since loading is not installing (ADR 19).
     */
    public static function installing(Slot ...$slots): self
    {
        return self::withinCapacity(self::of(...$slots)->slotsBySelector);
    }

    public function slotFor(Selector $selector): Slot
    {
        return $this->slotsBySelector[$selector->value()] ?? throw UnknownSelectorException::forSelector($selector);
    }

    /**
     * @return list<Slot>
     */
    public function slots(): array
    {
        return array_values($this->slotsBySelector);
    }

    public function dispense(Selector $selector): self
    {
        return $this->replacing($this->slotFor($selector)->dispense());
    }

    public function restock(Selector $selector, int $units): self
    {
        return $this->replacing($this->slotFor($selector)->restockedTo($units));
    }

    /**
     * @param array<int|string, int> $unitsBySelector
     */
    public function restockAll(array $unitsBySelector): self
    {
        $inventory = $this;
        foreach ($unitsBySelector as $selector => $units) {
            $inventory = $inventory->restock(Selector::fromString((string) $selector), $units);
        }

        return $inventory;
    }

    /**
     * @param array<string, Slot> $slotsBySelector
     */
    private static function withinCapacity(array $slotsBySelector): self
    {
        if (\count($slotsBySelector) > self::CAPACITY) {
            throw InventoryCapacityExceededException::forProducts(\count($slotsBySelector));
        }

        return new self($slotsBySelector);
    }

    private function replacing(Slot $slot): self
    {
        $slotsBySelector = $this->slotsBySelector;
        $slotsBySelector[$slot->product()->selector()->value()] = $slot;

        return new self($slotsBySelector);
    }
}
