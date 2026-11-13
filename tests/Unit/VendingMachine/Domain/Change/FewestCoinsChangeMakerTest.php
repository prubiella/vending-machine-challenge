<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Change;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Change\NoExactChangeException;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use PHPUnit\Framework\TestCase;

final class FewestCoinsChangeMakerTest extends TestCase
{
    public function testGivesNoCoinsForAZeroAmount(): void
    {
        $change = new FewestCoinsChangeMaker()->makeChange(Money::fromCents(0), CoinCollection::empty());

        self::assertTrue($change->isEmpty());
    }

    public function testGivesTheCoinThatMatchesTheAmount(): void
    {
        $available = CoinCollection::of(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents);

        $change = new FewestCoinsChangeMaker()->makeChange(Money::fromCents(10), $available);

        self::assertTrue($change->equals(CoinCollection::of(Coin::TenCents)));
    }

    public function testGivesTheCombinationWithTheFewestCoins(): void
    {
        $available = CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents, Coin::TenCents, Coin::TenCents, Coin::FiveCents);

        $change = new FewestCoinsChangeMaker()->makeChange(Money::fromCents(30), $available);

        self::assertTrue($change->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::FiveCents)));
    }

    public function testPrefersFewLargeCoinsToManySmallOnesThatAddUpToTheSame(): void
    {
        $available = CoinCollection::of(Coin::TwentyFiveCents, Coin::TwentyFiveCents, Coin::FiveCents, Coin::FiveCents, Coin::FiveCents, Coin::FiveCents, Coin::FiveCents);

        $change = new FewestCoinsChangeMaker()->makeChange(Money::fromCents(50), $available);

        self::assertTrue($change->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TwentyFiveCents)));
    }

    public function testUsesNoMoreCoinsOfAKindThanItHas(): void
    {
        $available = CoinCollection::of(Coin::TenCents, Coin::FiveCents, Coin::FiveCents);

        $change = new FewestCoinsChangeMaker()->makeChange(Money::fromCents(20), $available);

        self::assertTrue($change->equals(CoinCollection::of(Coin::TenCents, Coin::FiveCents, Coin::FiveCents)));
    }

    public function testFindsTheChangeWhereTakingTheLargestCoinFirstGetsStuck(): void
    {
        $available = CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents, Coin::TenCents, Coin::TenCents);

        $change = new FewestCoinsChangeMaker()->makeChange(Money::fromCents(30), $available);

        self::assertTrue($change->equals(CoinCollection::of(Coin::TenCents, Coin::TenCents, Coin::TenCents)));
    }

    public function testUsesWhicheverCoinsItIsGiven(): void
    {
        $available = CoinCollection::of(Coin::OneHundredCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents);

        $change = new FewestCoinsChangeMaker()->makeChange(Money::fromCents(100), $available);

        self::assertTrue($change->equals(CoinCollection::of(Coin::OneHundredCents)));
    }

    public function testRejectsAnAmountThatNoCombinationOfTheCoinsAddsUpTo(): void
    {
        $this->expectException(NoExactChangeException::class);

        new FewestCoinsChangeMaker()->makeChange(Money::fromCents(15), CoinCollection::of(Coin::TenCents, Coin::TenCents));
    }

    public function testRejectsAnAmountAboveTheTotalOfTheCoins(): void
    {
        $this->expectException(NoExactChangeException::class);

        new FewestCoinsChangeMaker()->makeChange(Money::fromCents(40), CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents));
    }
}
