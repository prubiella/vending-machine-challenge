<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Inventory;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Inventory\DuplicateSelectorException;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Inventory\UnknownSelectorException;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\Selector;
use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
{
    public function testFindsTheSlotOfASelector(): void
    {
        $water = Slot::of(self::product('WATER', 65), 5);
        $soda = Slot::of(self::product('SODA', 150), 2);

        $inventory = Inventory::of($water, $soda);

        self::assertSame($water, $inventory->slotFor(Selector::fromString('WATER')));
        self::assertSame($soda, $inventory->slotFor(Selector::fromString('SODA')));
    }

    public function testRejectsASelectorItDoesNotHave(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 5));

        $this->expectException(UnknownSelectorException::class);

        $inventory->slotFor(Selector::fromString('JUICE'));
    }

    public function testRejectsTwoSlotsWithTheSameSelector(): void
    {
        $this->expectException(DuplicateSelectorException::class);

        Inventory::of(
            Slot::of(self::product('WATER', 65), 5),
            Slot::of(self::product('WATER', 70), 1),
        );
    }

    private static function product(string $selector, int $cents): Product
    {
        return Product::of(Selector::fromString($selector), ucfirst(strtolower($selector)), Money::fromCents($cents));
    }
}
