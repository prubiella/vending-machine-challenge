<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Persistence;

use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Uuid\InvalidUuidException;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Coin\NegativeCoinCountException;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\MachineId;
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
    private const string MACHINE_ID = '01a1e2f0-aa80-70ea-832f-6110cbf86e31';

    public function testWritesEachPartOfTheMachineAsADocument(): void
    {
        $document = new VendingMachineMapper()->toDocument(self::machine());

        self::assertSame([
            '_id' => self::MACHINE_ID,
            'inventory' => [
                ['selector' => 'WATER', 'name' => 'Water', 'price_cents' => 65, 'units' => 3],
                ['selector' => 'SODA', 'name' => 'Soda', 'price_cents' => 150, 'units' => 0],
            ],
            'wallet_credit' => [5 => 0, 10 => 0, 25 => 1, 100 => 1],
            'wallet_stock' => [5 => 2, 10 => 1, 25 => 0, 100 => 0],
            'tray' => [
                'products' => [
                    ['selector' => 'SODA', 'name' => 'Soda', 'price_cents' => 150],
                    ['selector' => 'WATER', 'name' => 'Water', 'price_cents' => 65],
                ],
                'coins' => [5 => 0, 10 => 2, 25 => 0, 100 => 0],
            ],
        ], $document);
    }

    public function testReadsBackTheMachineFromItsDocument(): void
    {
        $mapper = new VendingMachineMapper();
        $machine = self::machine();

        self::assertEquals($machine, $mapper->fromDocument($mapper->toDocument($machine)));
    }

    public function testRefusesADocumentWithoutAPart(): void
    {
        $mapper = new VendingMachineMapper();
        $document = array_diff_key($mapper->toDocument(self::machine()), ['wallet_stock' => true]);

        $this->expectException(\UnexpectedValueException::class);

        $mapper->fromDocument($document);
    }

    public function testRefusesADocumentWithoutAnId(): void
    {
        $mapper = new VendingMachineMapper();
        $document = array_diff_key($mapper->toDocument(self::machine()), ['_id' => true]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('The stored vending machine has no "_id".');

        $mapper->fromDocument($document);
    }

    public function testRefusesADocumentWhoseIdIsNotAString(): void
    {
        $mapper = new VendingMachineMapper();
        $document = ['_id' => 1, ...array_diff_key($mapper->toDocument(self::machine()), ['_id' => true])];

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('The stored vending machine has no "_id".');

        $mapper->fromDocument($document);
    }

    public function testRefusesADocumentWhoseIdIsNotAUuid(): void
    {
        $mapper = new VendingMachineMapper();
        $document = ['_id' => 'not-a-uuid', ...array_diff_key($mapper->toDocument(self::machine()), ['_id' => true])];

        $this->expectException(InvalidUuidException::class);

        $mapper->fromDocument($document);
    }

    public function testLoadsAStoredTubeAbovePastItsCapacityBecauseSalesMayFillItBeyond(): void
    {
        $mapper = new VendingMachineMapper();
        $document = $mapper->toDocument(self::machine());
        $document['wallet_stock'] = [5 => 2, 10 => 1, 25 => Wallet::TUBE_CAPACITY + 3, 100 => 0];

        $loaded = $mapper->toDocument($mapper->fromDocument($document));

        self::assertSame(Wallet::TUBE_CAPACITY + 3, $loaded['wallet_stock'][25]);
    }

    public function testRefusesAStoredMachineThatBreaksARuleAsBrokenStorageNotAsARefusal(): void
    {
        $mapper = new VendingMachineMapper();
        $document = $mapper->toDocument(self::machine());
        $document['wallet_stock'] = [25 => -1];

        try {
            $mapper->fromDocument($document);
            self::fail('A stored machine with a negative count was loaded.');
        } catch (\UnexpectedValueException $broken) {
            self::assertInstanceOf(NegativeCoinCountException::class, $broken->getPrevious());
        }
    }

    public function testWritesEachPartOfTheMachineAsJson(): void
    {
        $row = new VendingMachineMapper()->toRow(self::machine());

        self::assertSame([
            'id' => self::MACHINE_ID,
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

    public function testRefusesARowWithoutAnId(): void
    {
        $mapper = new VendingMachineMapper();
        $row = array_diff_key($mapper->toRow(self::machine()), ['id' => true]);

        $this->expectException(\UnexpectedValueException::class);

        $mapper->fromRow($row);
    }

    public function testRefusesARowWhoseIdIsNotAUuid(): void
    {
        $mapper = new VendingMachineMapper();
        $row = ['id' => 'not-a-uuid', ...array_diff_key($mapper->toRow(self::machine()), ['id' => true])];

        $this->expectException(InvalidUuidException::class);

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
            MachineId::fromString(self::MACHINE_ID),
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
