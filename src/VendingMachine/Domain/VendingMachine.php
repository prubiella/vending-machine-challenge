<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain;

use App\VendingMachine\Domain\Change\ChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Event\CreditReturned;
use App\VendingMachine\Domain\Event\MachineServiced;
use App\VendingMachine\Domain\Event\ProductSold;
use App\VendingMachine\Domain\Event\StockRunningLow;
use App\VendingMachine\Domain\Event\VendingMachineEvent;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\Product\UnpayablePriceException;
use App\VendingMachine\Domain\Tray\Tray;
use App\VendingMachine\Domain\Wallet\Wallet;

final class VendingMachine
{
    /** @var list<VendingMachineEvent> */
    private array $events = [];

    private function __construct(
        private readonly MachineId $id,
        private Inventory $inventory,
        private Wallet $wallet,
        private Tray $tray,
    ) {
    }

    public static function install(MachineId $id, Product ...$products): self
    {
        self::refuseUnpayablePrices(...$products);
        $emptySlots = array_map(static fn (Product $product): Slot => Slot::of($product, 0), $products);

        return new self($id, Inventory::installing(...$emptySlots), Wallet::stocked(CoinCollection::empty()), Tray::empty());
    }

    /**
     * A machine stocked from its configuration when it is first installed: no technician serviced it,
     * so it records no event. The capacities bound it as they bound a service.
     *
     * @param array<int|string, int> $unitsBySelector
     */
    public static function installStocked(MachineId $id, array $unitsBySelector, CoinCollection $coins, Product ...$products): self
    {
        $machine = self::install($id, ...$products);
        $machine->replaceParts($machine->inventory->restockAll($unitsBySelector), $machine->wallet->restock($coins), $machine->tray);

        return $machine;
    }

    public static function restore(MachineId $id, Inventory $inventory, Wallet $wallet, Tray $tray): self
    {
        return new self($id, $inventory, $wallet, $tray);
    }

    public function insertCoin(Coin $coin): void
    {
        $this->wallet = $this->wallet->insert($coin);
    }

    public function select(Selector $selector, ChangeMaker $changeMaker): void
    {
        $slot = $this->inventory->slotFor($selector);
        $product = $slot->product();
        $inventory = $this->inventory->dispense($selector);
        $payout = $this->wallet->charge($product->price(), $changeMaker);
        $tray = $this->tray->addProduct($product)->addCoins($payout->coins());

        $this->replaceParts($inventory, $payout->wallet(), $tray);
        $this->record(new ProductSold($product, $payout->coins()));
        $slotLeft = $inventory->slotFor($selector);
        if ($slotLeft->isRunningLow() && !$slot->isRunningLow()) {
            $this->record(new StockRunningLow($selector, $slotLeft->units()));
        }
    }

    public function returnCoins(): void
    {
        $refund = $this->wallet->returnCredit();

        $this->wallet = $refund->wallet();
        $this->tray = $this->tray->addCoins($refund->coins());
        $this->recordCreditReturned($refund->coins());
    }

    public function collectTray(): void
    {
        $this->tray = Tray::empty();
    }

    /**
     * @param array<int|string, int> $unitsBySelector
     */
    public function service(TechnicianId $technicianId, array $unitsBySelector, CoinCollection $coins): void
    {
        $collectedFromCashBox = $this->wallet->cashBox();
        $inventory = $this->inventory->restockAll($unitsBySelector);
        $refund = $this->wallet->returnCredit();
        $wallet = $refund->wallet()->restock($coins);
        $tray = $this->tray->addCoins($refund->coins());

        $this->replaceParts($inventory, $wallet, $tray);
        $this->record(new MachineServiced($technicianId, $unitsBySelector, $coins, $collectedFromCashBox));
        $this->recordCreditReturned($refund->coins());
    }

    /**
     * Hands over the events recorded since the last call, oldest first, and forgets them, so the
     * repository saves each one once.
     *
     * @return list<VendingMachineEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function id(): MachineId
    {
        return $this->id;
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

    private function record(VendingMachineEvent $event): void
    {
        $this->events[] = $event;
    }

    private function recordCreditReturned(CoinCollection $coins): void
    {
        if (!$coins->isEmpty()) {
            $this->record(new CreditReturned($coins));
        }
    }

    // Product knows no coins, so the machine checks a price where a product enters it.
    private static function refuseUnpayablePrices(Product ...$products): void
    {
        $smallestCoin = Coin::smallest();
        foreach ($products as $product) {
            if (0 !== $product->price()->cents() % $smallestCoin->value) {
                throw UnpayablePriceException::forProduct($product, $smallestCoin);
            }
        }
    }

    private function replaceParts(Inventory $inventory, Wallet $wallet, Tray $tray): void
    {
        $this->inventory = $inventory;
        $this->wallet = $wallet;
        $this->tray = $tray;
    }
}
