<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Tray;

use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Product\Product;

final readonly class Pickup
{
    /**
     * @param list<Product> $products
     */
    private function __construct(private array $products, private CoinCollection $coins, private Tray $tray)
    {
    }

    /**
     * @param list<Product> $products
     */
    public static function of(array $products, CoinCollection $coins, Tray $tray): self
    {
        return new self($products, $coins, $tray);
    }

    /**
     * @return list<Product>
     */
    public function products(): array
    {
        return $this->products;
    }

    public function coins(): CoinCollection
    {
        return $this->coins;
    }

    public function tray(): Tray
    {
        return $this->tray;
    }
}
