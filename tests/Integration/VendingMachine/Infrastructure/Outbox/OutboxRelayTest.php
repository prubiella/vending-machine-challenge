<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Outbox;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Infrastructure\History\DbalMachineHistory;
use App\VendingMachine\Infrastructure\Notification\LowStockNotifier;
use App\VendingMachine\Infrastructure\Outbox\MachineOutbox;
use App\VendingMachine\Infrastructure\Outbox\OutboxEvent;
use App\VendingMachine\Infrastructure\Outbox\OutboxRelay;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OutboxRelayTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    #[Before]
    protected function forgetTheHistory(): void
    {
        self::getContainer()->get(Connection::class)->executeStatement('DELETE FROM machine_history');
    }

    public function testCopiesThePendingEventsToTheHistoryAndEmptiesTheOutbox(): void
    {
        $outbox = self::getContainer()->get(MachineOutbox::class);
        self::aSaleAndAReturn();
        $ids = array_map(static fn ($event): string => $event->id, $outbox->pending());

        $relayed = self::getContainer()->get(OutboxRelay::class)->relay();

        self::assertSame(2, $relayed);
        self::assertSame([], $outbox->pending());
        self::assertSame([[$ids[0], 'product_sold'], [$ids[1], 'credit_returned']], self::history());
    }

    public function testAPassWithNothingPendingRelaysNothing(): void
    {
        self::assertSame(0, self::getContainer()->get(OutboxRelay::class)->relay());
        self::assertSame([], self::history());
    }

    public function testACrashBeforeTheOutboxIsEmptiedRelaysTheEventsAgainAndTheHistoryKeepsThemOnce(): void
    {
        $outbox = self::getContainer()->get(MachineOutbox::class);
        $history = self::getContainer()->get(DbalMachineHistory::class);
        self::aSaleAndAReturn();
        $crashingOutbox = new class($outbox) implements MachineOutbox {
            public function __construct(private readonly MachineOutbox $outbox)
            {
            }

            public function pending(): array
            {
                return $this->outbox->pending();
            }

            public function remove(array $ids): void
            {
                throw new \RuntimeException('The relay crashed before emptying the outbox.');
            }
        };

        self::assertTheRelayCrashes(new OutboxRelay($crashingOutbox, $history, new SpyLowStockNotifier()));
        $historyAfterTheCrash = self::history();
        $relayedAgain = new OutboxRelay($outbox, $history, new SpyLowStockNotifier())->relay();

        self::assertCount(2, $historyAfterTheCrash);
        self::assertSame(2, $relayedAgain);
        self::assertSame($historyAfterTheCrash, self::history());
        self::assertSame([], $outbox->pending());
    }

    public function testNotifiesALowStockOnce(): void
    {
        $notifier = new SpyLowStockNotifier();
        $relay = self::relayNotifying($notifier);
        self::sellWaterUntilItRunsLow();

        $relay->relay();
        $relay->relay();

        self::assertSame([['WATER', 5]], $notifier->notified);
        self::assertContains('stock_running_low', array_column(self::history(), 1));
    }

    public function testNotifiesALowStockBeforeItsEventReachesTheHistory(): void
    {
        $failingNotifier = new class implements LowStockNotifier {
            public function stockRunningLow(string $selector, int $unitsLeft): void
            {
                throw new \RuntimeException('The notifier is down.');
            }
        };
        self::sellWaterUntilItRunsLow();

        self::assertTheRelayCrashes(self::relayNotifying($failingNotifier));

        self::assertNotContains('stock_running_low', array_column(self::history(), 1));
        self::assertContains('stock_running_low', self::pendingEventTypes());
    }

    public function testNeverNotifiesAnEventThatIsNotALowStock(): void
    {
        $notifier = new SpyLowStockNotifier();
        self::aSaleAndAReturn();

        self::relayNotifying($notifier)->relay();

        self::assertSame([], $notifier->notified);
        self::assertSame(['product_sold', 'credit_returned'], array_column(self::history(), 1));
    }

    public function testStopsOnALowStockWithoutItsUnitsAndLeavesItPending(): void
    {
        $notifier = new SpyLowStockNotifier();
        self::sellWaterUntilItRunsLow();
        $outbox = self::getContainer()->get(MachineOutbox::class);
        $withoutUnits = new class($outbox) implements MachineOutbox {
            public function __construct(private readonly MachineOutbox $outbox)
            {
            }

            public function pending(): array
            {
                return array_map(
                    static fn (OutboxEvent $event): OutboxEvent => new OutboxEvent($event->id, $event->type, $event->occurredAt, ['selector' => 'WATER']),
                    $this->outbox->pending(),
                );
            }

            public function remove(array $ids): void
            {
                $this->outbox->remove($ids);
            }
        };

        try {
            new OutboxRelay($withoutUnits, self::getContainer()->get(DbalMachineHistory::class), $notifier)->relay();
            self::fail('Expected UnexpectedValueException.');
        } catch (\UnexpectedValueException) {
            self::assertSame([], $notifier->notified);
            self::assertContains('stock_running_low', self::pendingEventTypes());
        }
    }

    private static function relayNotifying(LowStockNotifier $notifier): OutboxRelay
    {
        return new OutboxRelay(self::getContainer()->get(MachineOutbox::class), self::getContainer()->get(DbalMachineHistory::class), $notifier);
    }

    private static function assertTheRelayCrashes(OutboxRelay $relay): void
    {
        try {
            $relay->relay();
        } catch (\RuntimeException) {
            return;
        }

        self::fail('Expected the relay to crash.');
    }

    /**
     * @return list<string>
     */
    private static function pendingEventTypes(): array
    {
        return array_map(static fn (OutboxEvent $event): string => $event->type, self::getContainer()->get(MachineOutbox::class)->pending());
    }

    // The default machine holds 10 units of each product, so the fifth sale leaves 5.
    private static function sellWaterUntilItRunsLow(): void
    {
        $commandBus = self::getContainer()->get(CommandBus::class);
        for ($sale = 1; $sale <= 5; ++$sale) {
            $commandBus->dispatch(new InsertCoinCommand(100));
            $commandBus->dispatch(new SelectProductCommand('WATER'));
        }
    }

    private static function aSaleAndAReturn(): void
    {
        $commandBus = self::getContainer()->get(CommandBus::class);
        $commandBus->dispatch(new InsertCoinCommand(100));
        $commandBus->dispatch(new SelectProductCommand('WATER'));
        $commandBus->dispatch(new InsertCoinCommand(5));
        $commandBus->dispatch(new ReturnCoinsCommand());
    }

    /**
     * @return list<array{string, string}>
     */
    private static function history(): array
    {
        $rows = self::getContainer()->get(Connection::class)->fetchAllNumeric('SELECT id, type FROM machine_history ORDER BY occurred_at, id');

        return array_map(static function (array $row): array {
            [$id, $type] = $row;
            self::assertIsString($id);
            self::assertIsString($type);

            return [$id, $type];
        }, $rows);
    }
}
