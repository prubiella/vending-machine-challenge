<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\History;

use App\VendingMachine\Infrastructure\History\DbalMachineHistory;
use App\VendingMachine\Infrastructure\Outbox\OutboxEvent;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalMachineHistoryTest extends KernelTestCase
{
    private const string EVENT_ID = '00000000-0000-7000-8000-000000000001';

    #[Before]
    protected function forgetTheHistory(): void
    {
        self::getContainer()->get(Connection::class)->executeStatement('DELETE FROM machine_history');
    }

    public function testKeepsTheMachineTheTypeTheTimeInUtcAndThePayloadOfAnEvent(): void
    {
        $event = new OutboxEvent(self::EVENT_ID, 'stock_running_low', new \DateTimeImmutable('2026-03-01T11:15:30.123456+01:00'), ['selector' => 'WATER', 'units_left' => 5]);

        self::getContainer()->get(DbalMachineHistory::class)->record($event);

        $rows = self::rows();
        self::assertCount(1, $rows);
        self::assertSame(self::getContainer()->getParameter('vending_machine.id'), $rows[0]['machine_id']);
        self::assertSame(self::EVENT_ID, $rows[0]['id']);
        self::assertSame('stock_running_low', $rows[0]['type']);
        self::assertSame('2026-03-01 10:15:30.123456', $rows[0]['occurred_at']);
        self::assertIsString($rows[0]['payload']);
        self::assertSame(['selector' => 'WATER', 'units_left' => 5], json_decode($rows[0]['payload'], true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testKeepsAnEventRecordedTwiceOnce(): void
    {
        $history = self::getContainer()->get(DbalMachineHistory::class);
        $event = new OutboxEvent(self::EVENT_ID, 'credit_returned', new \DateTimeImmutable('2026-03-01T10:15:30Z'), ['coins' => [5 => 1]]);

        $history->record($event);
        $history->record($event);

        self::assertCount(1, self::rows());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(): array
    {
        return self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT id, machine_id, type, occurred_at, payload FROM machine_history');
    }
}
