<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Product;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Product\EmptyProductNameException;
use App\VendingMachine\Domain\Product\NonPositivePriceException;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\Selector;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('blankNames')]
    public function testRejectsABlankName(string $name): void
    {
        $this->expectException(EmptyProductNameException::class);

        Product::of(Selector::fromString('WATER'), $name, Money::fromCents(65));
    }

    /** @return iterable<string, array{string}> */
    public static function blankNames(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'whitespace characters' => ["\t\n"];
    }

    public function testRejectsAZeroPrice(): void
    {
        $this->expectException(NonPositivePriceException::class);

        Product::of(Selector::fromString('WATER'), 'Water', Money::fromCents(0));
    }
}
