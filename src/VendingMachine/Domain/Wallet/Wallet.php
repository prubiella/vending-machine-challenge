<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Wallet;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\ChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;

final readonly class Wallet
{
    private function __construct(private CoinCollection $credit, private CoinCollection $stock)
    {
    }

    public static function stocked(CoinCollection $stock): self
    {
        return new self(CoinCollection::empty(), $stock);
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

    public function restock(CoinCollection $stock): self
    {
        return new self($this->credit, $stock);
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
