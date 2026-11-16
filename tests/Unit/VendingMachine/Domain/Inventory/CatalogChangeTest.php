<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Inventory;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Inventory\CatalogChange;
use App\VendingMachine\Domain\Inventory\ContradictoryCatalogChangeException;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use PHPUnit\Framework\TestCase;

final class CatalogChangeTest extends TestCase
{
    public function testNoChangeInstallsAndRetiresNothing(): void
    {
        $change = CatalogChange::none();

        self::assertSame([], $change->installs());
        self::assertSame([], $change->retires());
    }

    public function testKeepsWhatItInstallsAndRetiresInTheOrderGiven(): void
    {
        $tea = Slot::installed(self::product('TEA'), 4);
        $coffee = Slot::installed(self::product('COFFEE'), 2);
        $water = Selector::fromString('WATER');

        $change = CatalogChange::of([$tea, $coffee], [$water]);

        self::assertSame([$tea, $coffee], $change->installs());
        self::assertSame([$water], $change->retires());
    }

    public function testRefusesToInstallTheSameSelectorTwice(): void
    {
        $this->expectException(ContradictoryCatalogChangeException::class);
        $this->expectExceptionMessage('The service installs TEA twice.');

        CatalogChange::of([Slot::installed(self::product('TEA'), 4), Slot::installed(self::product('TEA'), 1)], []);
    }

    public function testRefusesToRetireTheSameSelectorTwice(): void
    {
        $this->expectException(ContradictoryCatalogChangeException::class);
        $this->expectExceptionMessage('The service retires WATER twice.');

        CatalogChange::of([], [Selector::fromString('WATER'), Selector::fromString('WATER')]);
    }

    public function testRefusesToInstallAndRetireTheSameSelector(): void
    {
        $this->expectException(ContradictoryCatalogChangeException::class);
        $this->expectExceptionMessage('The service both installs and retires WATER.');

        CatalogChange::of([Slot::installed(self::product('WATER'), 4)], [Selector::fromString('WATER')]);
    }

    public function testRefusesUnitsForAProductItInstalls(): void
    {
        $change = CatalogChange::of([Slot::installed(self::product('TEA'), 4)], []);

        $this->expectException(ContradictoryCatalogChangeException::class);
        $this->expectExceptionMessage('The service sets the units of TEA apart from the ones it installs it with.');

        $change->refuseUnitsGivenTo(['WATER' => 3, 'TEA' => 2]);
    }

    public function testRefusesUnitsForAProductItRetires(): void
    {
        $change = CatalogChange::of([], [Selector::fromString('WATER')]);

        $this->expectException(ContradictoryCatalogChangeException::class);
        $this->expectExceptionMessage('The service sets the units of WATER, which it retires.');

        $change->refuseUnitsGivenTo(['WATER' => 0]);
    }

    public function testTakesUnitsForTheProductsItLeavesAlone(): void
    {
        $change = CatalogChange::of([Slot::installed(self::product('TEA'), 4)], [Selector::fromString('WATER')]);

        $this->expectNotToPerformAssertions();

        $change->refuseUnitsGivenTo(['SODA' => 3, 'JUICE' => 0]);
    }

    private static function product(string $selector): Product
    {
        return Product::of(Selector::fromString($selector), ProductName::fromString(ucfirst(strtolower($selector))), Money::fromCents(100));
    }
}
