<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Money;

use App\Shared\Domain\Money\InsufficientMoney;
use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\NegativeMoney;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testKeepsTheAmountInCents(): void
    {
        self::assertSame(65, Money::fromCents(65)->cents());
    }

    public function testAcceptsZero(): void
    {
        self::assertSame(0, Money::fromCents(0)->cents());
    }

    public function testRejectsANegativeAmount(): void
    {
        $this->expectException(NegativeMoney::class);

        Money::fromCents(-1);
    }

    public function testIsEqualToMoneyOfTheSameAmount(): void
    {
        self::assertTrue(Money::fromCents(25)->equals(Money::fromCents(25)));
    }

    public function testIsNotEqualToMoneyOfAnotherAmount(): void
    {
        self::assertFalse(Money::fromCents(25)->equals(Money::fromCents(10)));
    }

    public function testAddsTwoAmounts(): void
    {
        self::assertSame(90, Money::fromCents(65)->add(Money::fromCents(25))->cents());
    }

    public function testSubtractsASmallerAmount(): void
    {
        self::assertSame(35, Money::fromCents(100)->subtract(Money::fromCents(65))->cents());
    }

    public function testSubtractingTheSameAmountLeavesZero(): void
    {
        self::assertSame(0, Money::fromCents(65)->subtract(Money::fromCents(65))->cents());
    }

    public function testRejectsSubtractingMoreThanTheAmount(): void
    {
        $this->expectException(InsufficientMoney::class);

        Money::fromCents(65)->subtract(Money::fromCents(100));
    }

    /**
     * @return iterable<string, array{left: int, right: int, greaterThan: bool, greaterThanOrEqualTo: bool, lessThan: bool, lessThanOrEqualTo: bool}>
     */
    public static function comparisons(): iterable
    {
        yield 'smaller' => ['left' => 65, 'right' => 100, 'greaterThan' => false, 'greaterThanOrEqualTo' => false, 'lessThan' => true, 'lessThanOrEqualTo' => true];
        yield 'equal' => ['left' => 65, 'right' => 65, 'greaterThan' => false, 'greaterThanOrEqualTo' => true, 'lessThan' => false, 'lessThanOrEqualTo' => true];
        yield 'greater' => ['left' => 100, 'right' => 65, 'greaterThan' => true, 'greaterThanOrEqualTo' => true, 'lessThan' => false, 'lessThanOrEqualTo' => false];
    }

    #[DataProvider('comparisons')]
    public function testComparesTwoAmounts(
        int $left,
        int $right,
        bool $greaterThan,
        bool $greaterThanOrEqualTo,
        bool $lessThan,
        bool $lessThanOrEqualTo,
    ): void {
        $amount = Money::fromCents($left);
        $other = Money::fromCents($right);

        self::assertSame($greaterThan, $amount->isGreaterThan($other));
        self::assertSame($greaterThanOrEqualTo, $amount->isGreaterThanOrEqualTo($other));
        self::assertSame($lessThan, $amount->isLessThan($other));
        self::assertSame($lessThanOrEqualTo, $amount->isLessThanOrEqualTo($other));
    }
}
