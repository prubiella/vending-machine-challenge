<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Product;

use App\Shared\Domain\Money\Money;

final readonly class Product
{
    private function __construct(
        private Selector $selector,
        private string $name,
        private Money $price,
    ) {
    }

    public static function of(Selector $selector, string $name, Money $price): self
    {
        if ('' === trim($name)) {
            throw EmptyProductNameException::forSelector($selector);
        }

        if (0 === $price->cents()) {
            throw NonPositivePriceException::forSelector($selector);
        }

        return new self($selector, $name, $price);
    }

    public function selector(): Selector
    {
        return $this->selector;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }
}
