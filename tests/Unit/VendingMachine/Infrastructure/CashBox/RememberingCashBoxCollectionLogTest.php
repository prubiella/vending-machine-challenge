<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\CashBox;

use App\Tests\Unit\VendingMachine\Application\Command\ServiceMachine\RecordingCashBoxCollectionLog;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Infrastructure\CashBox\RememberingCashBoxCollectionLog;
use PHPUnit\Framework\TestCase;

final class RememberingCashBoxCollectionLogTest extends TestCase
{
    public function testHandsOverTheCoinsCollectedOnceAndForwardsThemToTheLogItDecorates(): void
    {
        $inner = new RecordingCashBoxCollectionLog();
        $log = new RememberingCashBoxCollectionLog($inner);
        $coins = CoinCollection::of(Coin::OneHundredCents, Coin::TenCents);

        $log->collected($coins);

        self::assertSame([$coins], $inner->collected);
        self::assertTrue($log->takeLastCollected()->equals($coins));
        self::assertTrue($log->takeLastCollected()->isEmpty());
    }

    public function testRemembersNothingBeforeAnythingIsCollected(): void
    {
        self::assertTrue(new RememberingCashBoxCollectionLog(new RecordingCashBoxCollectionLog())->takeLastCollected()->isEmpty());
    }

    public function testRemembersTheLastCollectionOnly(): void
    {
        $log = new RememberingCashBoxCollectionLog(new RecordingCashBoxCollectionLog());

        $log->collected(CoinCollection::of(Coin::TenCents));
        $log->collected(CoinCollection::of(Coin::FiveCents));

        self::assertTrue($log->takeLastCollected()->equals(CoinCollection::of(Coin::FiveCents)));
    }
}
