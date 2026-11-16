<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence;

use App\Shared\Domain\DomainException;
use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\Tray\Tray;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\Wallet\Wallet;

/**
 * @phpstan-type ProductRow array{selector: string, name: string, price_cents: int}
 * @phpstan-type SlotRow array{selector: string, name: string, price_cents: int, units: int}
 * @phpstan-type TrayRow array{products: list<ProductRow>, coins: array<int, int>}
 * @phpstan-type MachineParts array{inventory: list<SlotRow>, wallet_credit: array<int, int>, wallet_stock: array<int, int>, wallet_cash_box: array<int, int>, tray: TrayRow}
 * @phpstan-type MachineDocument array{_id: string, inventory: list<SlotRow>, wallet_credit: array<int, int>, wallet_stock: array<int, int>, wallet_cash_box: array<int, int>, tray: TrayRow}
 * @phpstan-type MachineRow array{id: string, inventory: string, wallet_credit: string, wallet_stock: string, wallet_cash_box: string, tray: string}
 */
final readonly class VendingMachineMapper
{
    private const array PARTS = ['inventory', 'wallet_credit', 'wallet_stock', 'wallet_cash_box', 'tray'];

    /**
     * @return MachineDocument
     */
    public function toDocument(VendingMachine $machine): array
    {
        return ['_id' => $machine->id()->value(), ...self::parts($machine)];
    }

    /**
     * @param array<string, mixed> $document
     */
    public function fromDocument(array $document): VendingMachine
    {
        $id = self::storedId($document, '_id');
        $document = self::withTheCashBoxOfAnOlderMachine($document);
        self::assertEveryPartIsPresent($document);

        return self::restore($id, $document);
    }

    /**
     * The id and each part of the document as a JSON column of the vending_machine row.
     *
     * @return MachineRow
     */
    public function toRow(VendingMachine $machine): array
    {
        return ['id' => $machine->id()->value(), ...array_map(self::encode(...), self::parts($machine))];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function fromRow(array $row): VendingMachine
    {
        $id = self::storedId($row, 'id');
        $parts = [];
        foreach (self::PARTS as $part) {
            if (\array_key_exists($part, $row) || 'wallet_cash_box' !== $part) {
                $parts[$part] = self::decode($row[$part] ?? null);
            }
        }
        $parts = self::withTheCashBoxOfAnOlderMachine($parts);
        self::assertEveryPartIsPresent($parts);

        return self::restore($id, $parts);
    }

    /**
     * A machine stored before the cash box existed has no such part and reads as one with an empty
     * cash box; a part that is there but null is broken storage and stays so.
     *
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    private static function withTheCashBoxOfAnOlderMachine(array $stored): array
    {
        if (!\array_key_exists('wallet_cash_box', $stored)) {
            $stored['wallet_cash_box'] = [];
        }

        return $stored;
    }

    /**
     * @param MachineParts $parts
     */
    private static function restore(string $id, array $parts): VendingMachine
    {
        $machineId = MachineId::fromString($id);

        // A stored machine whose parts break a rule is broken storage, not a request the domain
        // refuses: it must answer 500 and be logged as critical, not answer the refusal's status.
        try {
            return VendingMachine::restore(
                $machineId,
                self::inventory($parts['inventory']),
                Wallet::of(
                    CoinCollection::fromCounts($parts['wallet_credit']),
                    CoinCollection::fromCounts($parts['wallet_stock']),
                    CoinCollection::fromCounts($parts['wallet_cash_box']),
                ),
                self::tray($parts['tray']),
            );
        } catch (DomainException $brokenRule) {
            throw new \UnexpectedValueException('The stored vending machine breaks a rule: '.$brokenRule->getMessage(), 0, $brokenRule);
        }
    }

    /**
     * @param array<string, mixed> $stored
     */
    private static function storedId(array $stored, string $key): string
    {
        if (!\is_string($stored[$key] ?? null)) {
            throw new \UnexpectedValueException(\sprintf('The stored vending machine has no "%s".', $key));
        }

        return $stored[$key];
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @phpstan-assert MachineParts $stored
     */
    private static function assertEveryPartIsPresent(array $stored): void
    {
        foreach (self::PARTS as $part) {
            if (!\is_array($stored[$part] ?? null)) {
                throw new \UnexpectedValueException(\sprintf('The stored vending machine has no "%s".', $part));
            }
        }
    }

    /**
     * @return MachineParts
     */
    private static function parts(VendingMachine $machine): array
    {
        return [
            'inventory' => array_map(self::slotRow(...), $machine->inventory()->slots()),
            'wallet_credit' => $machine->wallet()->credit()->countsByCents(),
            'wallet_stock' => $machine->wallet()->stock()->countsByCents(),
            'wallet_cash_box' => $machine->wallet()->cashBox()->countsByCents(),
            'tray' => self::trayRow($machine->tray()),
        ];
    }

    /**
     * @return SlotRow
     */
    private static function slotRow(Slot $slot): array
    {
        return [...self::productRow($slot->product()), 'units' => $slot->units()];
    }

    /**
     * @return ProductRow
     */
    private static function productRow(Product $product): array
    {
        return [
            'selector' => $product->selector()->value(),
            'name' => $product->name()->value(),
            'price_cents' => $product->price()->cents(),
        ];
    }

    /**
     * @return TrayRow
     */
    private static function trayRow(Tray $tray): array
    {
        return [
            'products' => array_map(self::productRow(...), $tray->products()),
            'coins' => $tray->coins()->countsByCents(),
        ];
    }

    /**
     * @param list<SlotRow> $slots
     */
    private static function inventory(array $slots): Inventory
    {
        return Inventory::of(...array_map(
            static fn (array $slot): Slot => Slot::of(self::product($slot), $slot['units']),
            $slots,
        ));
    }

    /**
     * @param TrayRow $row
     */
    private static function tray(array $row): Tray
    {
        $tray = Tray::empty();
        foreach ($row['products'] as $product) {
            $tray = $tray->addProduct(self::product($product));
        }

        return $tray->addCoins(CoinCollection::fromCounts($row['coins']));
    }

    /**
     * @param ProductRow $row
     */
    private static function product(array $row): Product
    {
        return Product::of(
            Selector::fromString($row['selector']),
            ProductName::fromString($row['name']),
            Money::fromCents($row['price_cents']),
        );
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR);
    }

    private static function decode(mixed $column): mixed
    {
        if (!\is_string($column)) {
            throw new \UnexpectedValueException('A column of the vending_machine row is missing or is not text.');
        }

        return json_decode($column, true, flags: \JSON_THROW_ON_ERROR);
    }
}
