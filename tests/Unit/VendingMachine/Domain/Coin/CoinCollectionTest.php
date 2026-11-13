<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Coin;

use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Coin\CoinNotInCollectionException;
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
