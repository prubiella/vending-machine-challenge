<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Outbox;

use App\VendingMachine\Infrastructure\Outbox\OutboxEvent;
use App\VendingMachine\Infrastructure\Outbox\OutboxEventMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutboxEventMapperTest extends TestCase
{
    private const string ID = '00000000-0000-7000-8000-000000000001';

    public function testAnEventSurvivesAStoredDocumentWithItsTimeInUtc(): void
    {
        $mapper = new OutboxEventMapper();
        $event = self::event();

        $document = $mapper->toDocument($event);

        self::assertSame('2026-03-01T10:15:30.123456Z', $document['occurred_at']);
        self::assertEquals($event, $mapper->fromDocument($document));
    }

    public function testAnEventIsWrittenAsARowWithItsTimeInUtcAndItsPayloadAsJson(): void
    {
        $row = new OutboxEventMapper()->toRow(self::event());

        self::assertSame(self::ID, $row['id']);
        self::assertSame('stock_running_low', $row['type']);
        self::assertSame('2026-03-01 10:15:30.123456', $row['occurred_at']);
        self::assertSame('{"selector":"WATER","units_left":5}', $row['payload']);
    }

    /**
     * @param array<string, mixed>|string $entry
     */
    #[DataProvider('malformedEntries')]
    public function testRefusesAStoredEntryThatIsNotAnEvent(array|string $entry): void
    {
        $this->expectException(\UnexpectedValueException::class);

        new OutboxEventMapper()->fromDocument($entry);
    }

    /**
     * @return iterable<string, array{array<string, mixed>|string}>
     */
    public static function malformedEntries(): iterable
    {
        $valid = ['id' => self::ID, 'type' => 'credit_returned', 'occurred_at' => '2026-03-01T10:15:30.123456Z', 'payload' => []];

        yield 'not an array' => ['credit_returned'];
        yield 'no payload' => [array_diff_key($valid, ['payload' => true])];
        yield 'payload is not an array' => [[...$valid, 'payload' => 'text']];
        yield 'no id' => [array_diff_key($valid, ['id' => true])];
        yield 'no type' => [array_diff_key($valid, ['type' => true])];
        yield 'no time' => [array_diff_key($valid, ['occurred_at' => true])];
        yield 'unreadable time' => [[...$valid, 'occurred_at' => 'yesterday']];
        yield 'time of another format' => [[...$valid, 'occurred_at' => '2026-03-01 10:15:30.123456']];
    }

    private static function event(): OutboxEvent
    {
        return new OutboxEvent(
            self::ID,
            'stock_running_low',
            new \DateTimeImmutable('2026-03-01T11:15:30.123456+01:00'),
            ['selector' => 'WATER', 'units_left' => 5],
        );
    }
}
