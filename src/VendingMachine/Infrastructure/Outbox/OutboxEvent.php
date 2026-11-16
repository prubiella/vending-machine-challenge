<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Outbox;

/**
 * A domain event as the outbox keeps it: an id, its type, when it happened and its data, waiting
 * for the relay (ADR 20).
 */
final readonly class OutboxEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public \DateTimeImmutable $occurredAt,
        public array $payload,
    ) {
    }
}
