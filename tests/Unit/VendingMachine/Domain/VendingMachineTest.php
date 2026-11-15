<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\VendingMachine;
use PHPUnit\Framework\TestCase;

final class VendingMachineTest extends TestCase
{
    public function testAFreshlyInstalledMachineHasEmptySlotsNoCoinsAndAnEmptyTray(): void
    {
        $machine = self::installed();

        self::assertSame(0, $machine->inventory()->slotFor(Selector::fromString('WATER'))->units());
        self::assertSame(0, $machine->inventory()->slotFor(Selector::fromString('JUICE'))->units());
        self::assertSame(0, $machine->inventory()->slotFor(Selector::fromString('SODA'))->units());
        self::assertTrue($machine->wallet()->stock()->isEmpty());
        self::assertTrue($machine->wallet()->credit()->isEmpty());
        self::assertSame([], $machine->tray()->products());
        self::assertTrue($machine->tray()->coins()->isEmpty());
    }

    public function testInsertedCoinsJoinTheCredit(): void
    {
        $machine = self::installed();

        $machine->insertCoin(Coin::OneHundredCents);
        $machine->insertCoin(Coin::TwentyFiveCents);

        self::assertTrue($machine->wallet()->credit()->equals(CoinCollection::of(Coin::OneHundredCents, Coin::TwentyFiveCents)));
        self::assertTrue($machine->wallet()->stock()->isEmpty());
    }

    private static function installed(): VendingMachine
    {
        return VendingMachine::install(
            self::product('WATER', 'Water', 65),
            self::product('JUICE', 'Juice', 100),
            self::product('SODA', 'Soda', 150),
        );
    }

    private static function product(string $selector, string $name, int $priceInCents): Product
    {
        return Product::of(Selector::fromString($selector), ProductName::fromString($name), Money::fromCents($priceInCents));
    }
}
