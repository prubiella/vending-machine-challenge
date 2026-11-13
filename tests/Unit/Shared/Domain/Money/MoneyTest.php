<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Money;

use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\NegativeMoney;
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
}
