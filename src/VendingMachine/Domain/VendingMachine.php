<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain;

use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Tray\Tray;
use App\VendingMachine\Domain\Wallet\Wallet;

final class VendingMachine
{
    private function __construct(
        private Inventory $inventory,
        private Wallet $wallet,
        private Tray $tray,
    ) {
    }

    public static function install(Product ...$products): self
    {
        $emptySlots = array_map(static fn (Product $product): Slot => Slot::of($product, 0), $products);

        return new self(Inventory::of(...$emptySlots), Wallet::stocked(CoinCollection::empty()), Tray::empty());
    }

    public function insertCoin(Coin $coin): void
    {
        $this->wallet = $this->wallet->insert($coin);
    }

    public function inventory(): Inventory
    {
        return $this->inventory;
    }

    public function wallet(): Wallet
    {
        return $this->wallet;
    }

    public function tray(): Tray
    {
        return $this->tray;
    }
}
