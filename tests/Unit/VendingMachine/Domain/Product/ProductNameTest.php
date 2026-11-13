<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Product;

use App\VendingMachine\Domain\Product\EmptyProductNameException;
use App\VendingMachine\Domain\Product\ProductName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductNameTest extends TestCase
{
    public function testExposesItsValue(): void
    {
        self::assertSame('Diet Soda', ProductName::fromString('Diet Soda')->value());
    }

    #[DataProvider('blankNames')]
    public function testRejectsABlankName(string $name): void
    {
        $this->expectException(EmptyProductNameException::class);

        ProductName::fromString($name);
    }

    /** @return iterable<string, array{string}> */
    public static function blankNames(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'whitespace characters' => ["\t\n"];
    }
}
