<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Persistence;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\Tray\Tray;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\Wallet\Wallet;
use App\VendingMachine\Infrastructure\Persistence\VendingMachineMapper;
use PHPUnit\Framework\TestCase;

final class VendingMachineMapperTest extends TestCase
{
    public function testWritesEachPartOfTheMachineAsJson(): void
    {
        $row = new VendingMachineMapper()->toRow(self::machine());

        self::assertSame([
            'inventory' => '[{"selector":"WATER","name":"Water","price_cents":65,"units":3},{"selector":"SODA","name":"Soda","price_cents":150,"units":0}]',
            'wallet_credit' => '{"5":0,"10":0,"25":1,"100":1}',
            'wallet_stock' => '{"5":2,"10":1,"25":0,"100":0}',
            'tray' => '{"products":[{"selector":"SODA","name":"Soda","price_cents":150},{"selector":"WATER","name":"Water","price_cents":65}],"coins":{"5":0,"10":2,"25":0,"100":0}}',
        ], $row);
    }

    public function testReadsBackTheMachineItWrote(): void
    {
        $mapper = new VendingMachineMapper();
        $machine = self::machine();

        self::assertEquals($machine, $mapper->fromRow($mapper->toRow($machine)));
    }

    public function testRefusesAColumnThatIsNotJson(): void
    {
        $mapper = new VendingMachineMapper();
        $row = ['inventory' => 'not json', ...array_diff_key($mapper->toRow(self::machine()), ['inventory' => true])];

        $this->expectException(\JsonException::class);

        $mapper->fromRow($row);
    }

    public function testRefusesARowWithoutAColumn(): void
    {
        $mapper = new VendingMachineMapper();
        $row = array_diff_key($mapper->toRow(self::machine()), ['tray' => true]);

        $this->expectException(\UnexpectedValueException::class);

        $mapper->fromRow($row);
    }

    private static function machine(): VendingMachine
    {
        $water = self::product('WATER', 'Water', 65);
        $soda = self::product('SODA', 'Soda', 150);

        return VendingMachine::restore(
            Inventory::of(Slot::of($water, 3), Slot::of($soda, 0)),
            Wallet::of(
                CoinCollection::of(Coin::TwentyFiveCents, Coin::OneHundredCents),
                CoinCollection::of(Coin::FiveCents, Coin::FiveCents, Coin::TenCents),
            ),
            Tray::empty()->addProduct($soda)->addProduct($water)->addCoins(CoinCollection::of(Coin::TenCents, Coin::TenCents)),
        );
    }

    private static function product(string $selector, string $name, int $priceInCents): Product
    {
        return Product::of(Selector::fromString($selector), ProductName::fromString($name), Money::fromCents($priceInCents));
    }
}
