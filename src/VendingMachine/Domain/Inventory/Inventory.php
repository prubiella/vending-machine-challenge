<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\VendingMachine\Domain\Product\Selector;

final readonly class Inventory
{
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

    public function slotFor(Selector $selector): Slot
    {
        return $this->slotsBySelector[$selector->value()] ?? throw UnknownSelectorException::forSelector($selector);
    }
}
