<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Wallet;

use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
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
}
