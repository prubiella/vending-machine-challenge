<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Inventory;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Inventory\NegativeUnitsException;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Inventory\SoldOutException;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use PHPUnit\Framework\TestCase;

final class SlotTest extends TestCase
{
    public function testHoldsAProductAndItsUnits(): void
    {
        $product = self::water();

        $slot = Slot::of($product, 3);

        self::assertSame($product, $slot->product());
        self::assertSame(3, $slot->units());
        self::assertFalse($slot->isEmpty());
    }

    public function testASlotWithNoUnitsIsEmpty(): void
    {
        self::assertTrue(Slot::of(self::water(), 0)->isEmpty());
    }

    public function testRejectsNegativeUnits(): void
    {
        $this->expectException(NegativeUnitsException::class);

        Slot::of(self::water(), -1);
    }

    public function testDispensingLeavesOneUnitLess(): void
    {
        $slot = Slot::of(self::water(), 2);

        $dispensed = $slot->dispense();

        self::assertSame(1, $dispensed->units());
        self::assertSame(2, $slot->units());
        self::assertSame($slot->product(), $dispensed->product());
    }

    public function testCannotDispenseFromAnEmptySlot(): void
    {
        $this->expectException(SoldOutException::class);

        Slot::of(self::water(), 0)->dispense();
    }

    public function testChangingTheUnitsKeepsTheProduct(): void
    {
        $slot = Slot::of(self::water(), 2);

        $restocked = $slot->withUnits(10);

        self::assertSame(10, $restocked->units());
        self::assertSame(2, $slot->units());
        self::assertSame($slot->product(), $restocked->product());
    }

    public function testChangingTheUnitsRejectsANegativeCount(): void
    {
        $this->expectException(NegativeUnitsException::class);

        Slot::of(self::water(), 2)->withUnits(-1);
    }

    private static function water(): Product
    {
        return Product::of(Selector::fromString('WATER'), ProductName::fromString('Water'), Money::fromCents(65));
    }
}
