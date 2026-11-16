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

    // The coin that is never given as change (ADR 8): it has no tube to fill, so a sale puts it in
    // the cash box.
    private const Coin TAKINGS_COIN = Coin::OneHundredCents;

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

        $overflow = $this->creditThatDoesNotFitTheTubes();
        $stock = $this->stock->merge($this->credit->subtract($overflow));
        $change = $changeMaker->makeChange($credit->subtract($price), $stock);

        return Payout::of($change, new self(CoinCollection::empty(), $stock->subtract($change), $this->cashBox->merge($overflow)));
    }

    // Refuses a tube past its capacity, and empties the cash box: the technician takes what it held.
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

    private function creditThatDoesNotFitTheTubes(): CoinCollection
    {
        $overflow = [];
        foreach ($this->credit->countsByCents() as $cents => $count) {
            $coin = Coin::fromCents($cents);
            $room = self::TAKINGS_COIN === $coin ? 0 : max(0, self::TUBE_CAPACITY - $this->stock->countOf($coin));
            $overflow[$cents] = max(0, $count - $room);
        }

        return CoinCollection::fromCounts($overflow);
    }
}
