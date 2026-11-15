<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Wallet;

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
