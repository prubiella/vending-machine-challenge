<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Product;

use App\Shared\Domain\Money\Money;

final readonly class Product
{
    private function __construct(
        private Selector $selector,
        private ProductName $name,
        private Money $price,
    ) {
    }

    public static function of(Selector $selector, ProductName $name, Money $price): self
    {
        if (0 === $price->cents()) {
            throw NonPositivePriceException::forSelector($selector);
        }

        return new self($selector, $name, $price);
    }

    public function selector(): Selector
    {
        return $this->selector;
    }

    public function name(): ProductName
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }
}
