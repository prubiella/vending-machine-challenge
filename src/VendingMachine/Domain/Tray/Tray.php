<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Tray;

use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Product\Product;

final readonly class Tray
{
    /**
     * @param list<Product> $products
     */
    private function __construct(private array $products, private CoinCollection $coins)
    {
    }

    public static function empty(): self
    {
        return new self([], CoinCollection::empty());
    }

    public function addProduct(Product $product): self
    {
        return new self([...$this->products, $product], $this->coins);
    }

    public function addCoins(CoinCollection $coins): self
    {
        return new self($this->products, $this->coins->merge($coins));
    }

    public function collect(): Pickup
    {
        return Pickup::of($this->products, $this->coins, self::empty());
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
}
