<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Wallet;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Change\NoExactChangeException;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Wallet\InsufficientCreditException;
use App\VendingMachine\Domain\Wallet\Wallet;
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

        $payout = $wallet->charge(Money::fromCents(50), new FewestCoinsChangeMaker());

        self::assertTrue($payout->coins()->isEmpty());
        self::assertTrue($payout->wallet()->credit()->isEmpty());
        self::assertTrue($payout->wallet()->stock()->equals(CoinCollection::of(Coin::FiveCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents)));
    }

    public function testChargingGivesTheChangeFromTheStockAndTheInsertedCoins(): void
    {
        $wallet = Wallet::stocked(CoinCollection::of(Coin::TenCents, Coin::TenCents, Coin::FiveCents))->insert(Coin::TwentyFiveCents);

        $payout = $wallet->charge(Money::fromCents(5), new FewestCoinsChangeMaker());

        self::assertTrue($payout->coins()->equals(CoinCollection::of(Coin::TenCents, Coin::TenCents)));
        self::assertTrue($payout->wallet()->credit()->isEmpty());
        self::assertTrue($payout->wallet()->stock()->equals(CoinCollection::of(Coin::FiveCents, Coin::TwentyFiveCents)));
    }

    public function testChargingCanGiveChangeInTheCoinsJustInserted(): void
    {
        $wallet = Wallet::stocked(CoinCollection::empty())->insert(Coin::TwentyFiveCents)->insert(Coin::TwentyFiveCents)->insert(Coin::TwentyFiveCents);

        $payout = $wallet->charge(Money::fromCents(50), new FewestCoinsChangeMaker());

        self::assertTrue($payout->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents)));
        self::assertTrue($payout->wallet()->stock()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TwentyFiveCents)));
    }

    public function testAnInsertedOneStaysInTheStockAfterCharging(): void
    {
        $wallet = Wallet::stocked(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents))->insert(Coin::OneHundredCents);

        $payout = $wallet->charge(Money::fromCents(65), new FewestCoinsChangeMaker());

        self::assertTrue($payout->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents)));
        self::assertTrue($payout->wallet()->stock()->equals(CoinCollection::of(Coin::OneHundredCents)));
    }

    public function testNeverGivesAOneAsChangeEvenWhenTheStockHoldsOnes(): void
    {
        $wallet = Wallet::stocked(CoinCollection::of(Coin::OneHundredCents))->insert(Coin::OneHundredCents)->insert(Coin::OneHundredCents);

        $this->expectException(NoExactChangeException::class);

        $wallet->charge(Money::fromCents(100), new FewestCoinsChangeMaker());
    }

    public function testRefusesToChargeMoreThanTheCredit(): void
    {
        $wallet = Wallet::stocked(CoinCollection::empty())->insert(Coin::TwentyFiveCents);

        $this->expectException(InsufficientCreditException::class);

        $wallet->charge(Money::fromCents(65), new FewestCoinsChangeMaker());
    }

    public function testKeepsTheCreditWhenThereIsNoExactChange(): void
    {
        $wallet = Wallet::stocked(CoinCollection::empty())->insert(Coin::TwentyFiveCents);

        try {
            $wallet->charge(Money::fromCents(20), new FewestCoinsChangeMaker());
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
}
