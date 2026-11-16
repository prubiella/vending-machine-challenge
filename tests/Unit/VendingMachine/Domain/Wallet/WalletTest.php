<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Wallet;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Change\NoExactChangeException;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Wallet\CoinTubeCapacityExceededException;
use App\VendingMachine\Domain\Wallet\InsufficientCreditException;
use App\VendingMachine\Domain\Wallet\Wallet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WalletTest extends TestCase
{
    public function testAStockedWalletHoldsItsStockAndNoCredit(): void
    {
        $stock = CoinCollection::of(Coin::FiveCents, Coin::TenCents);

        $wallet = Wallet::stocked($stock);

        self::assertTrue($wallet->stock()->equals($stock));
        self::assertTrue($wallet->credit()->isEmpty());
    }

    public function testAWalletOfCreditAndStockHoldsBoth(): void
    {
        $credit = CoinCollection::of(Coin::OneHundredCents, Coin::TenCents);
        $stock = CoinCollection::of(Coin::FiveCents, Coin::TwentyFiveCents);

        $wallet = Wallet::of($credit, $stock);

        self::assertTrue($wallet->credit()->equals($credit));
        self::assertTrue($wallet->stock()->equals($stock));
    }

    public function testInsertingACoinAddsItToTheCreditAndLeavesTheStockAlone(): void
    {
        $stock = CoinCollection::of(Coin::FiveCents);
        $wallet = Wallet::stocked($stock);

        $inserted = $wallet->insert(Coin::TwentyFiveCents)->insert(Coin::OneHundredCents);

        self::assertTrue($inserted->credit()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::OneHundredCents)));
        self::assertTrue($inserted->stock()->equals($stock));
        self::assertTrue($wallet->credit()->isEmpty());
    }

    public function testChargingTheExactPriceGivesNoChangeAndMovesTheCreditToTheStock(): void
    {
        $wallet = Wallet::stocked(CoinCollection::of(Coin::FiveCents))->insert(Coin::TwentyFiveCents)->insert(Coin::TwentyFiveCents);

        $payout = $wallet->charge(Money::fromCents(50), self::changeMaker());

        self::assertTrue($payout->coins()->isEmpty());
        self::assertTrue($payout->wallet()->credit()->isEmpty());
        self::assertTrue($payout->wallet()->stock()->equals(CoinCollection::of(Coin::FiveCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents)));
    }

    public function testChargingGivesTheChangeFromTheStockAndTheInsertedCoins(): void
    {
        $wallet = Wallet::stocked(CoinCollection::of(Coin::TenCents, Coin::TenCents, Coin::FiveCents))->insert(Coin::TwentyFiveCents);

        $payout = $wallet->charge(Money::fromCents(5), self::changeMaker());

        self::assertTrue($payout->coins()->equals(CoinCollection::of(Coin::TenCents, Coin::TenCents)));
        self::assertTrue($payout->wallet()->credit()->isEmpty());
        self::assertTrue($payout->wallet()->stock()->equals(CoinCollection::of(Coin::FiveCents, Coin::TwentyFiveCents)));
    }

    public function testChargingCanGiveChangeInTheCoinsJustInserted(): void
    {
        $wallet = Wallet::stocked(CoinCollection::empty())->insert(Coin::TwentyFiveCents)->insert(Coin::TwentyFiveCents)->insert(Coin::TwentyFiveCents);

        $payout = $wallet->charge(Money::fromCents(50), self::changeMaker());

        self::assertTrue($payout->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents)));
        self::assertTrue($payout->wallet()->stock()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TwentyFiveCents)));
    }

    public function testAnInsertedOneGoesToTheCashBoxAfterCharging(): void
    {
        $wallet = Wallet::stocked(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents))->insert(Coin::OneHundredCents);

        $payout = $wallet->charge(Money::fromCents(65), self::changeMaker());

        self::assertTrue($payout->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents)));
        self::assertTrue($payout->wallet()->stock()->isEmpty());
        self::assertTrue($payout->wallet()->cashBox()->equals(CoinCollection::of(Coin::OneHundredCents)));
    }

    public function testACoinThatFitsItsTubeStaysInTheStockAfterCharging(): void
    {
        $wallet = Wallet::stocked(CoinCollection::fromCounts([25 => Wallet::TUBE_CAPACITY - 1]))->insert(Coin::TwentyFiveCents);

        $payout = $wallet->charge(Money::fromCents(25), self::changeMaker());

        self::assertSame(Wallet::TUBE_CAPACITY, $payout->wallet()->stock()->countOf(Coin::TwentyFiveCents));
        self::assertTrue($payout->wallet()->cashBox()->isEmpty());
    }

    public function testTheCoinsThatDoNotFitTheirFullTubeGoToTheCashBox(): void
    {
        $wallet = Wallet::stocked(CoinCollection::fromCounts([25 => Wallet::TUBE_CAPACITY - 1, 10 => 3]))
            ->insert(Coin::TwentyFiveCents)->insert(Coin::TwentyFiveCents)->insert(Coin::TwentyFiveCents)->insert(Coin::TenCents);

        $payout = $wallet->charge(Money::fromCents(85), self::changeMaker());

        self::assertTrue($payout->coins()->isEmpty());
        self::assertSame(Wallet::TUBE_CAPACITY, $payout->wallet()->stock()->countOf(Coin::TwentyFiveCents));
        self::assertSame(4, $payout->wallet()->stock()->countOf(Coin::TenCents));
        self::assertTrue($payout->wallet()->cashBox()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TwentyFiveCents)));
    }

    public function testATubeAlreadyPastItsCapacityTakesNoMoreCoins(): void
    {
        $wallet = Wallet::of(CoinCollection::of(Coin::FiveCents), CoinCollection::fromCounts([5 => Wallet::TUBE_CAPACITY + 7]));

        $payout = $wallet->charge(Money::fromCents(5), self::changeMaker());

        self::assertSame(Wallet::TUBE_CAPACITY + 7, $payout->wallet()->stock()->countOf(Coin::FiveCents));
        self::assertTrue($payout->wallet()->cashBox()->equals(CoinCollection::of(Coin::FiveCents)));
    }

    public function testEveryCoinTheChangeMakerNeverGivesGoesToTheCashBox(): void
    {
        $wallet = Wallet::stocked(CoinCollection::empty())->insert(Coin::TenCents)->insert(Coin::TwentyFiveCents)->insert(Coin::OneHundredCents);
        $changeMaker = new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents);

        $payout = $wallet->charge(Money::fromCents(135), $changeMaker);

        self::assertTrue($payout->coins()->isEmpty());
        self::assertTrue($payout->wallet()->stock()->equals(CoinCollection::of(Coin::TenCents)));
        self::assertTrue($payout->wallet()->cashBox()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::OneHundredCents)));
    }

    public function testACoinTheChangeMakerGivesFillsItsTubeEvenWhenItIsTheOne(): void
    {
        $wallet = Wallet::stocked(CoinCollection::empty())->insert(Coin::OneHundredCents);
        $changeMaker = new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents, Coin::OneHundredCents);

        $payout = $wallet->charge(Money::fromCents(100), $changeMaker);

        self::assertTrue($payout->wallet()->stock()->equals(CoinCollection::of(Coin::OneHundredCents)));
        self::assertTrue($payout->wallet()->cashBox()->isEmpty());
    }

    public function testCoinsThatOverflowedTheirTubeCannotGiveChange(): void
    {
        $wallet = Wallet::stocked(CoinCollection::fromCounts([25 => Wallet::TUBE_CAPACITY]))
            ->insert(Coin::TwentyFiveCents)->insert(Coin::TwentyFiveCents);

        $this->expectException(NoExactChangeException::class);

        $wallet->charge(Money::fromCents(10), self::changeMaker());
    }

    public function testChargingAddsToTheCoinsAlreadyInTheCashBox(): void
    {
        $cashBox = CoinCollection::of(Coin::OneHundredCents);
        $wallet = Wallet::of(CoinCollection::empty(), CoinCollection::empty(), $cashBox)->insert(Coin::OneHundredCents);

        $payout = $wallet->charge(Money::fromCents(100), self::changeMaker());

        self::assertTrue($payout->wallet()->cashBox()->equals(CoinCollection::of(Coin::OneHundredCents, Coin::OneHundredCents)));
    }

    public function testChangeNeverComesFromTheCashBox(): void
    {
        $wallet = Wallet::of(CoinCollection::of(Coin::TwentyFiveCents), CoinCollection::empty(), CoinCollection::fromCounts([5 => 10, 10 => 10]));

        $this->expectException(NoExactChangeException::class);

        $wallet->charge(Money::fromCents(10), self::changeMaker());
    }

    public function testReturningTheCreditLeavesTheCashBoxAlone(): void
    {
        $cashBox = CoinCollection::of(Coin::OneHundredCents);
        $wallet = Wallet::of(CoinCollection::empty(), CoinCollection::empty(), $cashBox)->insert(Coin::TenCents);

        self::assertTrue($wallet->returnCredit()->wallet()->cashBox()->equals($cashBox));
    }

    public function testRestockingEmptiesTheCashBox(): void
    {
        $wallet = Wallet::of(CoinCollection::empty(), CoinCollection::empty(), CoinCollection::of(Coin::OneHundredCents));

        self::assertTrue($wallet->restock(CoinCollection::of(Coin::FiveCents))->cashBox()->isEmpty());
    }

    public function testRestockingStillSetsTheTubeOfTheOne(): void
    {
        $restocked = Wallet::stocked(CoinCollection::empty())->restock(CoinCollection::of(Coin::OneHundredCents));

        self::assertSame(1, $restocked->stock()->countOf(Coin::OneHundredCents));
    }

    public function testAWalletWithoutACashBoxHasAnEmptyOne(): void
    {
        self::assertTrue(Wallet::stocked(CoinCollection::empty())->cashBox()->isEmpty());
        self::assertTrue(Wallet::of(CoinCollection::empty(), CoinCollection::empty())->cashBox()->isEmpty());
    }

    /**
     * @param list<Coin> $stock
     * @param list<Coin> $inserted
     */
    #[DataProvider('charges')]
    public function testChargingConservesTheCoins(array $stock, array $inserted, int $priceInCents): void
    {
        $wallet = Wallet::stocked(CoinCollection::of(...$stock));
        foreach ($inserted as $coin) {
            $wallet = $wallet->insert($coin);
        }

        $payout = $wallet->charge(Money::fromCents($priceInCents), self::changeMaker());

        $coinsBefore = $wallet->stock()->merge($wallet->credit());
        $coinsAfter = $payout->wallet()->stock()->merge($payout->coins())->merge($payout->wallet()->cashBox());
        self::assertTrue($coinsAfter->equals($coinsBefore));
        self::assertSame($wallet->credit()->total()->cents() - $priceInCents, $payout->coins()->total()->cents());
    }

    /** @return iterable<string, array{list<Coin>, list<Coin>, int}> */
    public static function charges(): iterable
    {
        yield 'exact price' => [[Coin::FiveCents], [Coin::TwentyFiveCents, Coin::TwentyFiveCents], 50];
        yield 'change from the coins just inserted' => [[], [Coin::TwentyFiveCents, Coin::TenCents, Coin::TenCents], 35];
        yield 'an inserted 1' => [[Coin::TwentyFiveCents, Coin::TenCents, Coin::FiveCents], [Coin::OneHundredCents], 65];
        yield '1 coins already in the stock' => [[Coin::OneHundredCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents, Coin::TenCents], [Coin::OneHundredCents], 50];
        yield 'a full tube' => [[...array_fill(0, Wallet::TUBE_CAPACITY, Coin::TwentyFiveCents), Coin::TenCents], [Coin::TwentyFiveCents, Coin::TwentyFiveCents, Coin::TenCents], 40];
        yield 'a large stock' => [
            [...array_fill(0, 10, Coin::FiveCents), ...array_fill(0, 10, Coin::TenCents), ...array_fill(0, 10, Coin::TwentyFiveCents)],
            [Coin::OneHundredCents, Coin::OneHundredCents],
            65,
        ];
    }

    public function testRefusesToChargeMoreThanTheCredit(): void
    {
        $wallet = Wallet::stocked(CoinCollection::empty())->insert(Coin::TwentyFiveCents);

        $this->expectException(InsufficientCreditException::class);

        $wallet->charge(Money::fromCents(65), self::changeMaker());
    }

    public function testKeepsTheCreditWhenThereIsNoExactChange(): void
    {
        $wallet = Wallet::stocked(CoinCollection::empty())->insert(Coin::TwentyFiveCents);

        try {
            $wallet->charge(Money::fromCents(20), self::changeMaker());
            self::fail('Expected NoExactChangeException.');
        } catch (NoExactChangeException) {
            self::assertTrue($wallet->credit()->equals(CoinCollection::of(Coin::TwentyFiveCents)));
            self::assertTrue($wallet->stock()->isEmpty());
        }
    }

    public function testRestockingKeepsTheCredit(): void
    {
        $wallet = Wallet::stocked(CoinCollection::of(Coin::FiveCents))->insert(Coin::TenCents);
        $newStock = CoinCollection::of(Coin::TwentyFiveCents, Coin::TwentyFiveCents);

        $restocked = $wallet->restock($newStock);

        self::assertTrue($restocked->stock()->equals($newStock));
        self::assertTrue($restocked->credit()->equals(CoinCollection::of(Coin::TenCents)));
        self::assertTrue($wallet->stock()->equals(CoinCollection::of(Coin::FiveCents)));
    }

    public function testATubeTakesAsManyCoinsAsItsCapacity(): void
    {
        $restocked = Wallet::stocked(CoinCollection::empty())->restock(CoinCollection::fromCounts([25 => Wallet::TUBE_CAPACITY]));

        self::assertSame(Wallet::TUBE_CAPACITY, $restocked->stock()->countsByCents()[25]);
    }

    public function testRefusesToRestockATubePastItsCapacity(): void
    {
        $this->expectExceptionObject(CoinTubeCapacityExceededException::forCoin(Coin::TenCents, 501));

        Wallet::stocked(CoinCollection::empty())->restock(CoinCollection::fromCounts([5 => 10, 10 => Wallet::TUBE_CAPACITY + 1]));
    }

    public function testReturningTheCreditGivesBackExactlyTheInsertedCoinsIncludingTheOne(): void
    {
        $stock = CoinCollection::of(Coin::FiveCents);
        $wallet = Wallet::stocked($stock)->insert(Coin::OneHundredCents)->insert(Coin::TenCents)->insert(Coin::TenCents);

        $payout = $wallet->returnCredit();

        self::assertTrue($payout->coins()->equals(CoinCollection::of(Coin::OneHundredCents, Coin::TenCents, Coin::TenCents)));
        self::assertTrue($payout->wallet()->credit()->isEmpty());
        self::assertTrue($payout->wallet()->stock()->equals($stock));
        self::assertSame(120, $wallet->credit()->total()->cents());
    }

    private static function changeMaker(): FewestCoinsChangeMaker
    {
        return new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents);
    }
}
