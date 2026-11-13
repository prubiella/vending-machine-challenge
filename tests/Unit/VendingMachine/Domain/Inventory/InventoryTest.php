<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Inventory;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Inventory\DuplicateSelectorException;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\NegativeUnitsException;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Inventory\SoldOutException;
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

    public function testDispensingReturnsAnInventoryWithOneUnitLess(): void
    {
        $water = Selector::fromString('WATER');
        $soda = Selector::fromString('SODA');
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 5), Slot::of(self::product('SODA', 150), 2));

        $dispensed = $inventory->dispense($water);

        self::assertSame(4, $dispensed->slotFor($water)->units());
        self::assertSame(2, $dispensed->slotFor($soda)->units());
        self::assertSame(5, $inventory->slotFor($water)->units());
    }

    public function testCannotDispenseFromASoldOutSlot(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 0));

        $this->expectException(SoldOutException::class);

        $inventory->dispense(Selector::fromString('WATER'));
    }

    public function testCannotDispenseAnUnknownSelector(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 5));

        $this->expectException(UnknownSelectorException::class);

        $inventory->dispense(Selector::fromString('JUICE'));
    }

    public function testRestockingSetsTheUnitsOfOneSlot(): void
    {
        $water = Selector::fromString('WATER');
        $soda = Selector::fromString('SODA');
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 0), Slot::of(self::product('SODA', 150), 2));

        $restocked = $inventory->restock($water, 10);

        self::assertSame(10, $restocked->slotFor($water)->units());
        self::assertSame(2, $restocked->slotFor($soda)->units());
        self::assertSame(0, $inventory->slotFor($water)->units());
    }

    public function testCannotRestockAnUnknownSelector(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 5));

        $this->expectException(UnknownSelectorException::class);

        $inventory->restock(Selector::fromString('JUICE'), 3);
    }

    public function testCannotRestockANegativeNumberOfUnits(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 5));

        $this->expectException(NegativeUnitsException::class);

        $inventory->restock(Selector::fromString('WATER'), -1);
    }

    private static function product(string $selector, int $cents): Product
    {
        return Product::of(Selector::fromString($selector), ucfirst(strtolower($selector)), Money::fromCents($cents));
    }
}
