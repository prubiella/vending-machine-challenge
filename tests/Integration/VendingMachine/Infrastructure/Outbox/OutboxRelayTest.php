<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Outbox;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Infrastructure\History\DbalMachineHistory;
use App\VendingMachine\Infrastructure\Outbox\MachineOutbox;
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

        try {
            new OutboxRelay($crashingOutbox, $history)->relay();
            self::fail('Expected the relay to crash.');
        } catch (\RuntimeException) {
        }
        $historyAfterTheCrash = self::history();
        $relayedAgain = new OutboxRelay($outbox, $history)->relay();

        self::assertCount(2, $historyAfterTheCrash);
        self::assertSame(2, $relayedAgain);
        self::assertSame($historyAfterTheCrash, self::history());
        self::assertSame([], $outbox->pending());
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
