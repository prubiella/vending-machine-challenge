<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Product;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\Selector;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testExposesItsSelectorNameAndPrice(): void
    {
        $selector = Selector::fromString('WATER');
        $price = Money::fromCents(65);

        $product = Product::of($selector, 'Water', $price);

        self::assertSame($selector, $product->selector());
        self::assertSame('Water', $product->name());
        self::assertSame($price, $product->price());
    }
}
