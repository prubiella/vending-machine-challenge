<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\VendingMachine\Domain\Product\Product;

final readonly class Slot
{
    private function __construct(
        private Product $product,
        private int $units,
    ) {
    }

    public static function of(Product $product, int $units): self
    {
        if ($units < 0) {
            throw NegativeUnitsException::fromUnits($units);
        }

        return new self($product, $units);
    }

    public function product(): Product
    {
        return $this->product;
    }

    public function units(): int
    {
        return $this->units;
    }

    public function isEmpty(): bool
    {
        return 0 === $this->units;
    }

    public function dispense(): self
    {
        if ($this->isEmpty()) {
            throw SoldOutException::forSelector($this->product->selector());
        }

        return new self($this->product, $this->units - 1);
    }

    public function withUnits(int $units): self
    {
        return self::of($this->product, $units);
    }
}
