<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
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
 * @phpstan-type MachineDocument array{inventory: list<SlotRow>, wallet_credit: array<int, int>, wallet_stock: array<int, int>, tray: TrayRow}
 * @phpstan-type MachineRow array{inventory: string, wallet_credit: string, wallet_stock: string, tray: string}
 */
final readonly class VendingMachineMapper
{
    private const array PARTS = ['inventory', 'wallet_credit', 'wallet_stock', 'tray'];

    /**
     * @return MachineDocument
     */
    public function toDocument(VendingMachine $machine): array
    {
        return [
            'inventory' => array_map(self::slotRow(...), $machine->inventory()->slots()),
            'wallet_credit' => $machine->wallet()->credit()->countsByCents(),
            'wallet_stock' => $machine->wallet()->stock()->countsByCents(),
            'tray' => self::trayRow($machine->tray()),
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    public function fromDocument(array $document): VendingMachine
    {
        foreach (self::PARTS as $part) {
            if (!\is_array($document[$part] ?? null)) {
                throw new \UnexpectedValueException(\sprintf('The stored vending machine has no "%s".', $part));
            }
        }

        /** @var MachineDocument $machine */
        $machine = $document;

        return VendingMachine::restore(
            self::inventory($machine['inventory']),
            Wallet::of(CoinCollection::fromCounts($machine['wallet_credit']), CoinCollection::fromCounts($machine['wallet_stock'])),
            self::tray($machine['tray']),
        );
    }

    /**
     * Each part of the document as a JSON column of the vending_machine row.
     *
     * @return MachineRow
     */
    public function toRow(VendingMachine $machine): array
    {
        return array_map(self::encode(...), $this->toDocument($machine));
    }

    /**
     * @param array<string, mixed> $row
     */
    public function fromRow(array $row): VendingMachine
    {
        $document = [];
        foreach (self::PARTS as $part) {
            $document[$part] = self::decode($row[$part] ?? null);
        }

        return $this->fromDocument($document);
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
