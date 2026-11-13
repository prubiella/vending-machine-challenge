<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Product;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Product\NonPositivePriceException;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testExposesItsSelectorNameAndPrice(): void
    {
        $selector = Selector::fromString('WATER');
        $name = ProductName::fromString('Water');
        $price = Money::fromCents(65);

        $product = Product::of($selector, $name, $price);

        self::assertSame($selector, $product->selector());
        self::assertSame($name, $product->name());
        self::assertSame($price, $product->price());
    }

    public function testRejectsAZeroPrice(): void
    {
        $this->expectException(NonPositivePriceException::class);

        Product::of(Selector::fromString('WATER'), ProductName::fromString('Water'), Money::fromCents(0));
    }
}
