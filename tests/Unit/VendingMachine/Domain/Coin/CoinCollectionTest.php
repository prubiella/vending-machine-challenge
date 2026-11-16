<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Coin;

use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Coin\CoinNotInCollectionException;
use App\VendingMachine\Domain\Coin\NegativeCoinCountException;
use App\VendingMachine\Domain\Coin\UnknownCoinException;
use PHPUnit\Framework\TestCase;

final class CoinCollectionTest extends TestCase
{
    public function testAnEmptyCollectionHoldsNoCoins(): void
    {
        $coins = CoinCollection::empty();

        self::assertTrue($coins->isEmpty());
        self::assertSame(0, $coins->countOf(Coin::TwentyFiveCents));
        self::assertSame(0, $coins->total()->cents());
    }

    public function testCountsEachCoinItHolds(): void
    {
        $coins = CoinCollection::of(Coin::TwentyFiveCents, Coin::FiveCents, Coin::TwentyFiveCents);

        self::assertSame(2, $coins->countOf(Coin::TwentyFiveCents));
        self::assertSame(1, $coins->countOf(Coin::FiveCents));
        self::assertSame(0, $coins->countOf(Coin::OneHundredCents));
    }

    public function testIsBuiltFromTheCountOfEachCoinByItsCents(): void
    {
        $coins = CoinCollection::fromCounts([5 => 2, 25 => 1, 100 => 0]);

        self::assertTrue($coins->equals(CoinCollection::of(Coin::FiveCents, Coin::FiveCents, Coin::TwentyFiveCents)));
    }

    public function testHoldsAHugeCountWithoutAnElementPerCoin(): void
    {
        $memoryBefore = memory_get_usage();

        $collection = CoinCollection::fromCounts([5 => 1_000_000_000]);

        self::assertSame(1_000_000_000, $collection->countsByCents()[5]);
        self::assertLessThan(1_000_000, memory_get_usage() - $memoryBefore);
    }

    public function testRejectsACountOfAnUnknownCoin(): void
    {
        $this->expectException(UnknownCoinException::class);

        CoinCollection::fromCounts([5 => 2, 50 => 1]);
    }

    public function testRejectsANegativeCount(): void
    {
        $this->expectException(NegativeCoinCountException::class);
        $this->expectExceptionMessageIs('A coin of 10 cents cannot have a negative count, got -1.');

        CoinCollection::fromCounts([10 => -1]);
    }

    public function testGivesTheCountOfEveryCoinByItsCentsFromTheSmallest(): void
    {
        $coins = CoinCollection::of(Coin::TwentyFiveCents, Coin::FiveCents, Coin::TwentyFiveCents);

        self::assertSame([5 => 1, 10 => 0, 25 => 2, 100 => 0], $coins->countsByCents());
    }

    public function testTotalsTheAmountOfItsCoins(): void
    {
        $coins = CoinCollection::of(Coin::OneHundredCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents, Coin::TenCents, Coin::FiveCents);

        self::assertSame(165, $coins->total()->cents());
    }

    public function testAddingACoinReturnsACollectionWithOneMoreOfIt(): void
    {
        $coins = CoinCollection::of(Coin::TenCents)->add(Coin::TenCents)->add(Coin::OneHundredCents);

        self::assertFalse($coins->isEmpty());
        self::assertSame(2, $coins->countOf(Coin::TenCents));
        self::assertSame(1, $coins->countOf(Coin::OneHundredCents));
    }

    public function testAddingACoinLeavesTheOriginalCollectionUnchanged(): void
    {
        $original = CoinCollection::empty();

        $original->add(Coin::FiveCents);

        self::assertTrue($original->isEmpty());
    }

    public function testRemovingACoinReturnsACollectionWithOneLessOfIt(): void
    {
        $coins = CoinCollection::of(Coin::TenCents, Coin::TenCents, Coin::FiveCents)->remove(Coin::TenCents);

        self::assertSame(1, $coins->countOf(Coin::TenCents));
        self::assertSame(1, $coins->countOf(Coin::FiveCents));
    }

    public function testRemovingTheLastCoinLeavesAnEmptyCollection(): void
    {
        $coins = CoinCollection::of(Coin::TenCents)->remove(Coin::TenCents);

        self::assertTrue($coins->isEmpty());
        self::assertTrue($coins->equals(CoinCollection::empty()));
    }

    public function testRemovingACoinLeavesTheOriginalCollectionUnchanged(): void
    {
        $original = CoinCollection::of(Coin::TenCents);

        $original->remove(Coin::TenCents);

        self::assertSame(1, $original->countOf(Coin::TenCents));
    }

    public function testRejectsRemovingACoinItDoesNotHold(): void
    {
        $this->expectException(CoinNotInCollectionException::class);

        CoinCollection::of(Coin::TenCents)->remove(Coin::TwentyFiveCents);
    }

    public function testMergingJoinsTheCoinsOfBothCollections(): void
    {
        $coins = CoinCollection::of(Coin::FiveCents, Coin::TenCents);
        $other = CoinCollection::of(Coin::FiveCents, Coin::OneHundredCents);

        $merged = $coins->merge($other);

        self::assertTrue($merged->equals(CoinCollection::of(Coin::FiveCents, Coin::FiveCents, Coin::TenCents, Coin::OneHundredCents)));
        self::assertSame(15, $coins->total()->cents());
        self::assertSame(105, $other->total()->cents());
    }

    public function testSubtractingTakesTheCoinsOfTheOtherCollectionOut(): void
    {
        $coins = CoinCollection::of(Coin::FiveCents, Coin::FiveCents, Coin::TenCents, Coin::OneHundredCents);
        $other = CoinCollection::of(Coin::FiveCents, Coin::OneHundredCents);

        $remaining = $coins->subtract($other);

        self::assertTrue($remaining->equals(CoinCollection::of(Coin::FiveCents, Coin::TenCents)));
        self::assertSame(120, $coins->total()->cents());
    }

    public function testSubtractingEveryCoinLeavesAnEmptyCollection(): void
    {
        $coins = CoinCollection::of(Coin::FiveCents, Coin::TenCents);

        self::assertTrue($coins->subtract($coins)->isEmpty());
    }

    public function testRejectsSubtractingMoreCoinsThanItHolds(): void
    {
        $this->expectException(CoinNotInCollectionException::class);

        CoinCollection::of(Coin::FiveCents)->subtract(CoinCollection::of(Coin::FiveCents, Coin::FiveCents));
    }

    public function testRejectsSubtractingACoinItDoesNotHold(): void
    {
        $this->expectException(CoinNotInCollectionException::class);

        CoinCollection::of(Coin::FiveCents)->subtract(CoinCollection::of(Coin::TenCents));
    }

    public function testCollectionsWithTheSameCoinsInAnyOrderAreEqual(): void
    {
        $coins = CoinCollection::of(Coin::FiveCents, Coin::OneHundredCents, Coin::FiveCents);

        self::assertTrue($coins->equals(CoinCollection::of(Coin::OneHundredCents, Coin::FiveCents, Coin::FiveCents)));
        self::assertTrue($coins->equals(CoinCollection::empty()->add(Coin::FiveCents)->add(Coin::FiveCents)->add(Coin::OneHundredCents)));
    }

    public function testCollectionsWithDifferentCountsAreNotEqual(): void
    {
        $coins = CoinCollection::of(Coin::FiveCents, Coin::FiveCents);

        self::assertFalse($coins->equals(CoinCollection::of(Coin::FiveCents)));
        self::assertFalse($coins->equals(CoinCollection::of(Coin::TenCents)));
        self::assertFalse($coins->equals(CoinCollection::empty()));
    }
}
