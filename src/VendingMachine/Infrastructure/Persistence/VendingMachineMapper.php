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
 * @phpstan-type MachineRow array{inventory: string, wallet_credit: string, wallet_stock: string, tray: string}
 */
final readonly class VendingMachineMapper
{
    /**
     * @return MachineRow
     */
    public function toRow(VendingMachine $machine): array
    {
        return [
            'inventory' => self::encode(array_map(self::slotRow(...), $machine->inventory()->slots())),
            'wallet_credit' => self::encode($machine->wallet()->credit()->countsByCents()),
            'wallet_stock' => self::encode($machine->wallet()->stock()->countsByCents()),
            'tray' => self::encode(self::trayRow($machine->tray())),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function fromRow(array $row): VendingMachine
    {
        /** @var list<SlotRow> $slots */
        $slots = self::decode($row['inventory'] ?? null);
        /** @var array<int, int> $credit */
        $credit = self::decode($row['wallet_credit'] ?? null);
        /** @var array<int, int> $stock */
        $stock = self::decode($row['wallet_stock'] ?? null);
        /** @var TrayRow $tray */
        $tray = self::decode($row['tray'] ?? null);

        return VendingMachine::restore(
            self::inventory($slots),
            Wallet::of(CoinCollection::fromCounts($credit), CoinCollection::fromCounts($stock)),
            self::tray($tray),
        );
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
