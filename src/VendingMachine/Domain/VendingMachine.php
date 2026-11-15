<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain;

use App\VendingMachine\Domain\Change\ChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\Selector;
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

    public function select(Selector $selector, ChangeMaker $changeMaker): void
    {
        $product = $this->inventory->slotFor($selector)->product();
        $inventory = $this->inventory->dispense($selector);
        $payout = $this->wallet->charge($product->price(), $changeMaker);
        $tray = $this->tray->addProduct($product)->addCoins($payout->coins());

        $this->replaceParts($inventory, $payout->wallet(), $tray);
    }

    public function returnCoins(): void
    {
        $refund = $this->wallet->returnCredit();

        $this->wallet = $refund->wallet();
        $this->tray = $this->tray->addCoins($refund->coins());
    }

    public function collectTray(): void
    {
        $this->tray = Tray::empty();
    }

    /**
     * @param array<int|string, int> $unitsBySelector
     */
    public function service(array $unitsBySelector, CoinCollection $coins): void
    {
        $inventory = $this->restocked($unitsBySelector);
        $refund = $this->wallet->returnCredit();
        $tray = $this->tray->addCoins($refund->coins());

        $this->replaceParts($inventory, $refund->wallet()->restock($coins), $tray);
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

    /**
     * @param array<int|string, int> $unitsBySelector
     */
    private function restocked(array $unitsBySelector): Inventory
    {
        $inventory = $this->inventory;
        foreach ($unitsBySelector as $selector => $units) {
            $inventory = $inventory->restock(Selector::fromString((string) $selector), $units);
        }

        return $inventory;
    }

    private function replaceParts(Inventory $inventory, Wallet $wallet, Tray $tray): void
    {
        $this->inventory = $inventory;
        $this->wallet = $wallet;
        $this->tray = $tray;
    }
}
