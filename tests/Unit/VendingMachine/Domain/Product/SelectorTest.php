<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Product;

use App\VendingMachine\Domain\Product\InvalidSelectorException;
use App\VendingMachine\Domain\Product\Selector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SelectorTest extends TestCase
{
    public function testExposesItsValue(): void
    {
        self::assertSame('WATER', Selector::fromString('WATER')->value());
    }

    public function testAcceptsDigitsAndUnderscores(): void
    {
        self::assertSame('SODA_2', Selector::fromString('SODA_2')->value());
    }

    #[DataProvider('invalidValues')]
    public function testRejectsAValueThatIsNotCanonical(string $value): void
    {
        $this->expectException(InvalidSelectorException::class);

        Selector::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'lowercase' => ['water'];
        yield 'surrounding spaces' => [' WATER '];
        yield 'inner space' => ['DIET SODA'];
        yield 'symbol' => ['SODA-2'];
        yield 'trailing newline' => ["WATER\n"];
    }

    public function testEqualsASelectorWithTheSameValue(): void
    {
        self::assertTrue(Selector::fromString('JUICE')->equals(Selector::fromString('JUICE')));
        self::assertFalse(Selector::fromString('JUICE')->equals(Selector::fromString('SODA')));
    }
}
