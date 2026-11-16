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

    private function __construct(private CoinCollection $credit, private CoinCollection $stock)
    {
    }

    public static function stocked(CoinCollection $stock): self
    {
        return new self(CoinCollection::empty(), $stock);
    }

    public static function of(CoinCollection $credit, CoinCollection $stock): self
    {
        return new self($credit, $stock);
    }

    public function insert(Coin $coin): self
    {
        return new self($this->credit->add($coin), $this->stock);
    }

    public function returnCredit(): Payout
    {
        return Payout::of($this->credit, new self(CoinCollection::empty(), $this->stock));
    }

    public function charge(Money $price, ChangeMaker $changeMaker): Payout
    {
        $credit = $this->credit->total();
        if ($credit->isLessThan($price)) {
            throw InsufficientCreditException::forPrice($price, $credit);
        }

        $stock = $this->stock->merge($this->credit);
        $change = $changeMaker->makeChange($credit->subtract($price), $stock);

        return Payout::of($change, new self(CoinCollection::empty(), $stock->subtract($change)));
    }

    // A sale is not bounded: the customer's coins join the stock and may take a tube past its
    // capacity, as the overflow of a real machine goes to its cash box.
    public function restock(CoinCollection $stock): self
    {
        foreach ($stock->countsByCents() as $cents => $count) {
            if ($count > self::TUBE_CAPACITY) {
                throw CoinTubeCapacityExceededException::forCoin(Coin::fromCents($cents), $count);
            }
        }

        return new self($this->credit, $stock);
    }

    public function topUp(CoinCollection $coins): self
    {
        return new self($this->credit, $this->stock->merge($coins));
    }

    public function credit(): CoinCollection
    {
        return $this->credit;
    }

    public function stock(): CoinCollection
    {
        return $this->stock;
    }
}
