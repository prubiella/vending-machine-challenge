<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Product\Product;

final readonly class ProductSold implements VendingMachineEvent
{
    public function __construct(
        private Product $product,
        private CoinCollection $change,
    ) {
    }

    public function product(): Product
    {
        return $this->product;
    }

    public function change(): CoinCollection
    {
        return $this->change;
    }
}
