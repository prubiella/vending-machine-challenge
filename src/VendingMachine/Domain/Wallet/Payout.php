<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Wallet;

use App\VendingMachine\Domain\Coin\CoinCollection;

final readonly class Payout
{
    private function __construct(private CoinCollection $coins, private Wallet $wallet)
    {
    }

    public static function of(CoinCollection $coins, Wallet $wallet): self
    {
        return new self($coins, $wallet);
    }

    public function coins(): CoinCollection
    {
        return $this->coins;
    }

    public function wallet(): Wallet
    {
        return $this->wallet;
    }
}
