<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Tray;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\Tray\Tray;
use PHPUnit\Framework\TestCase;

final class TrayTest extends TestCase
{
    public function testANewTrayHoldsNothing(): void
    {
        $tray = Tray::empty();

        self::assertSame([], $tray->products());
        self::assertTrue($tray->coins()->isEmpty());
    }

    public function testAddingAProductPutsItInTheTray(): void
    {
        $water = self::water();

        $tray = Tray::empty()->addProduct($water);

        self::assertSame([$water], $tray->products());
    }

    public function testSeveralProductsPileUpInOrder(): void
    {
        $water = self::water();
        $soda = self::soda();

        $tray = Tray::empty()->addProduct($water)->addProduct($soda)->addProduct($water);

        self::assertSame([$water, $soda, $water], $tray->products());
    }

    public function testAddingCoinsPutsThemInTheTray(): void
    {
        $coins = CoinCollection::of(Coin::TwentyFiveCents, Coin::FiveCents);

        $tray = Tray::empty()->addCoins($coins);

        self::assertTrue($tray->coins()->equals($coins));
    }

    public function testSeveralCoinAdditionsAccumulate(): void
    {
        $tray = Tray::empty()
            ->addCoins(CoinCollection::of(Coin::TwentyFiveCents))
            ->addCoins(CoinCollection::of(Coin::TwentyFiveCents, Coin::OneHundredCents));

        self::assertTrue($tray->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TwentyFiveCents, Coin::OneHundredCents)));
    }

    public function testAddingAnEmptyCollectionKeepsWhatTheTrayHolds(): void
    {
        $water = self::water();
        $coins = CoinCollection::of(Coin::FiveCents);

        $tray = Tray::empty()->addProduct($water)->addCoins($coins)->addCoins(CoinCollection::empty());

        self::assertSame([$water], $tray->products());
        self::assertTrue($tray->coins()->equals($coins));
    }

    public function testAddingReturnsANewTrayAndLeavesTheOriginalUntouched(): void
    {
        $original = Tray::empty();

        $original->addProduct(self::water())->addCoins(CoinCollection::of(Coin::FiveCents));

        self::assertSame([], $original->products());
        self::assertTrue($original->coins()->isEmpty());
    }

    private static function water(): Product
    {
        return Product::of(Selector::fromString('WATER'), ProductName::fromString('Water'), Money::fromCents(65));
    }

    private static function soda(): Product
    {
        return Product::of(Selector::fromString('SODA'), ProductName::fromString('Soda'), Money::fromCents(150));
    }
}
