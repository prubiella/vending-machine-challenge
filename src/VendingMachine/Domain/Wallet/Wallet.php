<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Wallet;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\ChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;

final readonly class Wallet
{
    public const int TUBE_CAPACITY = 500;

    private function __construct(
        private CoinCollection $credit,
        private CoinCollection $stock,
        private CoinCollection $cashBox,
    ) {
    }

    public static function stocked(CoinCollection $stock): self
    {
        return new self(CoinCollection::empty(), $stock, CoinCollection::empty());
    }

    public static function of(CoinCollection $credit, CoinCollection $stock, ?CoinCollection $cashBox = null): self
    {
        return new self($credit, $stock, $cashBox ?? CoinCollection::empty());
    }

    public function insert(Coin $coin): self
    {
        return new self($this->credit->add($coin), $this->stock, $this->cashBox);
    }

    public function returnCredit(): Payout
    {
        return Payout::of($this->credit, new self(CoinCollection::empty(), $this->stock, $this->cashBox));
    }

    public function charge(Money $price, ChangeMaker $changeMaker): Payout
    {
        $credit = $this->credit->total();
        if ($credit->isLessThan($price)) {
            throw InsufficientCreditException::forPrice($price, $credit);
        }

        $takings = $this->creditThatGoesToTheCashBox($changeMaker);
        $stock = $this->stock->merge($this->credit->subtract($takings));
        $change = $changeMaker->makeChange($credit->subtract($price), $stock);

        return Payout::of($change, new self(
            CoinCollection::empty(),
            $stock->subtract($change),
            $this->cashBox->merge($takings),
        ));
    }

    // The technician servicing the machine takes what the cash box held, so a restock empties it.
    public function restock(CoinCollection $stock): self
    {
        foreach ($stock->countsByCents() as $cents => $count) {
            if ($count > self::TUBE_CAPACITY) {
                throw CoinTubeCapacityExceededException::forCoin(Coin::fromCents($cents), $count);
            }
        }

        return new self($this->credit, $stock, CoinCollection::empty());
    }

    public function topUp(CoinCollection $coins): self
    {
        return new self($this->credit, $this->stock->merge($coins), $this->cashBox);
    }

    public function credit(): CoinCollection
    {
        return $this->credit;
    }

    public function stock(): CoinCollection
    {
        return $this->stock;
    }

    public function cashBox(): CoinCollection
    {
        return $this->cashBox;
    }

    // A coin the change maker never gives has no tube to fill, and a coin that gives change goes to
    // its tube only up to the capacity.
    private function creditThatGoesToTheCashBox(ChangeMaker $changeMaker): CoinCollection
    {
        $takings = [];
        foreach ($this->credit->countsByCents() as $cents => $count) {
            $coin = Coin::fromCents($cents);
            $room = $changeMaker->gives($coin) ? max(0, self::TUBE_CAPACITY - $this->stock->countOf($coin)) : 0;
            $takings[$cents] = max(0, $count - $room);
        }

        return CoinCollection::fromCounts($takings);
    }
}
