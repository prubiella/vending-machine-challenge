<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Outbox;

/**
 * An outbox event as an entry of the machine's MongoDB document or as a row of machine_history.
 * Times are stored in UTC, to the microsecond.
 *
 * @phpstan-type OutboxEntry array{id: string, type: string, occurred_at: string, payload: array<string, mixed>}
 * @phpstan-type OutboxRow array{id: string, type: string, occurred_at: string, payload: string}
 */
final readonly class OutboxEventMapper
{
    private const string DOCUMENT_TIME = 'Y-m-d\TH:i:s.u\Z';
    private const string ROW_TIME = 'Y-m-d H:i:s.u';

    /**
     * @return OutboxEntry
     */
    public function toDocument(OutboxEvent $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type,
            'occurred_at' => self::utc($event->occurredAt)->format(self::DOCUMENT_TIME),
            'payload' => $event->payload,
        ];
    }

    public function fromDocument(mixed $entry): OutboxEvent
    {
        if (!\is_array($entry) || !\is_array($entry['payload'] ?? null)) {
            throw new \UnexpectedValueException('An outbox entry of the stored vending machine has no payload.');
        }

        return self::event($entry, self::DOCUMENT_TIME, self::stringKeyed($entry['payload']));
    }

    /**
     * @return OutboxRow
     */
    public function toRow(OutboxEvent $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type,
            'occurred_at' => self::utc($event->occurredAt)->format(self::ROW_TIME),
            'payload' => json_encode($event->payload, \JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param array<mixed>         $stored
     * @param array<string, mixed> $payload
     */
    private static function event(array $stored, string $timeFormat, array $payload): OutboxEvent
    {
        $id = $stored['id'] ?? null;
        $type = $stored['type'] ?? null;
        $occurredAt = $stored['occurred_at'] ?? null;
        if (!\is_string($id) || !\is_string($type) || !\is_string($occurredAt)) {
            throw new \UnexpectedValueException('An outbox event is missing its id, type or time.');
        }
        $time = \DateTimeImmutable::createFromFormat($timeFormat, $occurredAt, new \DateTimeZone('UTC'));
        if (false === $time) {
            throw new \UnexpectedValueException(\sprintf('The outbox event %s has an unreadable time.', $id));
        }

        return new OutboxEvent($id, $type, $time, $payload);
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $payload): array
    {
        $stringKeyed = [];
        foreach ($payload as $key => $value) {
            $stringKeyed[(string) $key] = $value;
        }

        return $stringKeyed;
    }

    private static function utc(\DateTimeImmutable $time): \DateTimeImmutable
    {
        return $time->setTimezone(new \DateTimeZone('UTC'));
    }
}
