<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Outbox;

use App\VendingMachine\Infrastructure\History\DbalMachineHistory;

/**
 * Copies the pending events to the history, then removes them from the outbox. A crash in between
 * relays them again on the next pass, and the history keeps each once: at least once delivery to
 * an idempotent consumer (ADR 20).
 */
final readonly class OutboxRelay
{
    public function __construct(
        private MachineOutbox $outbox,
        private DbalMachineHistory $history,
    ) {
    }

    /**
     * @return int how many events it relayed
     */
    public function relay(): int
    {
        $pending = $this->outbox->pending();
        foreach ($pending as $event) {
            $this->history->record($event);
        }
        $this->outbox->remove(array_map(static fn (OutboxEvent $event): string => $event->id, $pending));

        return \count($pending);
    }
}
