<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Inventory;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Inventory\CatalogChange;
use App\VendingMachine\Domain\Inventory\DuplicateSelectorException;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\InventoryCapacityExceededException;
use App\VendingMachine\Domain\Inventory\NegativeUnitsException;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Inventory\SoldOutException;
use App\VendingMachine\Domain\Inventory\UnknownSelectorException;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
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

    public function testListsItsSlotsInTheOrderTheyWereGiven(): void
    {
        $soda = Slot::of(self::product('SODA', 150), 2);
        $water = Slot::of(self::product('WATER', 65), 5);

        $inventory = Inventory::of($soda, $water);

        self::assertSame([$soda, $water], $inventory->slots());
    }

    public function testKeepsTheOrderOfItsSlotsWhenOneIsRestocked(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('SODA', 150), 2), Slot::of(self::product('WATER', 65), 5));

        $restocked = $inventory->restock(Selector::fromString('SODA'), 7);

        $selectors = array_map(static fn (Slot $slot): string => $slot->product()->selector()->value(), $restocked->slots());
        self::assertSame(['SODA', 'WATER'], $selectors);
        self::assertSame(7, $restocked->slots()[0]->units());
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

    public function testRestockingSeveralSelectorsSetsEachAndLeavesTheOthers(): void
    {
        $inventory = Inventory::of(
            Slot::of(self::product('WATER', 65), 1),
            Slot::of(self::product('JUICE', 100), 2),
            Slot::of(self::product('SODA', 150), 3),
        );

        $restocked = $inventory->restockAll(['WATER' => 10, 'SODA' => 0]);

        self::assertSame(10, $restocked->slotFor(Selector::fromString('WATER'))->units());
        self::assertSame(2, $restocked->slotFor(Selector::fromString('JUICE'))->units());
        self::assertSame(0, $restocked->slotFor(Selector::fromString('SODA'))->units());
    }

    public function testRestockingSeveralSelectorsAcceptsOnesMadeOnlyOfDigits(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('42', 50), 0));

        $restocked = $inventory->restockAll(['42' => 3]);

        self::assertSame(3, $restocked->slotFor(Selector::fromString('42'))->units());
    }

    public function testCannotRestockSeveralSelectorsWhenOneIsUnknown(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 5));

        $this->expectException(UnknownSelectorException::class);

        $inventory->restockAll(['WATER' => 1, 'COFFEE' => 4]);
    }

    public function testAChangeOfCatalogAddsTheSlotOfANewProductAfterTheOthers(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 5));

        $changed = $inventory->applying(CatalogChange::of([Slot::installed(self::product('TEA', 120), 4)], []));

        self::assertSame(['WATER', 'TEA'], self::selectorsOf($changed));
        self::assertSame(4, $changed->slotFor(Selector::fromString('TEA'))->units());
        self::assertSame(['WATER'], self::selectorsOf($inventory));
    }

    public function testInstallingASelectorItHasRedefinesItsProductInPlace(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 5), Slot::of(self::product('SODA', 150), 2));

        $changed = $inventory->applying(CatalogChange::of([Slot::installed(self::product('WATER', 70), 9)], []));

        self::assertSame(['WATER', 'SODA'], self::selectorsOf($changed));
        self::assertSame(70, $changed->slotFor(Selector::fromString('WATER'))->product()->price()->cents());
        self::assertSame(9, $changed->slotFor(Selector::fromString('WATER'))->units());
    }

    public function testRetiringAProductTakesItsSlotAndItsUnitsAway(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 5), Slot::of(self::product('SODA', 150), 2));

        $changed = $inventory->applying(CatalogChange::of([], [Selector::fromString('WATER')]));

        self::assertSame(['SODA'], self::selectorsOf($changed));
    }

    public function testCannotRetireAnUnknownSelector(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 65), 5));

        $this->expectException(UnknownSelectorException::class);

        $inventory->applying(CatalogChange::of([], [Selector::fromString('COFFEE')]));
    }

    public function testHoldsAsManyProductsAsItsCapacity(): void
    {
        $changed = Inventory::of()->applying(CatalogChange::of(self::slots(Inventory::CAPACITY), []));

        self::assertCount(Inventory::CAPACITY, $changed->slots());
    }

    public function testRefusesAChangeOfCatalogThatLeavesMoreProductsThanItsCapacity(): void
    {
        $full = Inventory::of(...self::slots(Inventory::CAPACITY));

        $this->expectException(InventoryCapacityExceededException::class);

        $full->applying(CatalogChange::of([Slot::installed(self::product('TEA', 120), 1)], []));
    }

    public function testCountsItsCapacityAfterTheRetiredProductsLeave(): void
    {
        $full = Inventory::of(...self::slots(Inventory::CAPACITY));

        $changed = $full->applying(CatalogChange::of([Slot::installed(self::product('TEA', 120), 1)], [Selector::fromString('P1')]));

        self::assertCount(Inventory::CAPACITY, $changed->slots());
        self::assertSame(1, $changed->slotFor(Selector::fromString('TEA'))->units());
    }

    public function testInstallingRefusesMoreProductsThanItsCapacity(): void
    {
        $this->expectException(InventoryCapacityExceededException::class);

        Inventory::installing(...self::slots(Inventory::CAPACITY + 1));
    }

    public function testInstallingHoldsAsManyProductsAsItsCapacity(): void
    {
        self::assertCount(Inventory::CAPACITY, Inventory::installing(...self::slots(Inventory::CAPACITY))->slots());
    }

    /**
     * @return list<Slot>
     */
    private static function slots(int $count): array
    {
        return array_map(static fn (int $number): Slot => Slot::of(self::product('P'.$number, 100), 0), range(1, $count));
    }

    /**
     * @return list<string>
     */
    private static function selectorsOf(Inventory $inventory): array
    {
        return array_map(static fn (Slot $slot): string => $slot->product()->selector()->value(), $inventory->slots());
    }

    private static function product(string $selector, int $cents): Product
    {
        return Product::of(Selector::fromString($selector), ProductName::fromString(ucfirst(strtolower($selector))), Money::fromCents($cents));
    }
}
