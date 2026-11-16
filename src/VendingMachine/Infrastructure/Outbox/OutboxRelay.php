<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Outbox;

use App\VendingMachine\Infrastructure\History\DbalMachineHistory;
use App\VendingMachine\Infrastructure\Notification\LowStockNotifier;

/**
 * Copies the pending events to the history, then removes them from the outbox. A crash in between
 * relays them again on the next pass, and the history keeps each once: at least once delivery to
 * an idempotent consumer (ADR 20). A low stock is notified before its event reaches the history,
 * so a crash may repeat a notice but never loses one.
 */
final readonly class OutboxRelay
{
    public function __construct(
        private MachineOutbox $outbox,
        private DbalMachineHistory $history,
        private LowStockNotifier $lowStockNotifier,
    ) {
    }

    /**
     * @return int how many events it relayed
     */
    public function relay(): int
    {
        $pending = $this->outbox->pending();
        foreach ($pending as $event) {
            if (OutboxEventFactory::STOCK_RUNNING_LOW === $event->type) {
                $this->notifyLowStock($event);
            }
            $this->history->record($event);
        }
        $this->outbox->remove(array_map(static fn (OutboxEvent $event): string => $event->id, $pending));

        return \count($pending);
    }

    private function notifyLowStock(OutboxEvent $event): void
    {
        $selector = $event->payload['selector'] ?? null;
        $unitsLeft = $event->payload['units_left'] ?? null;
        if (!\is_string($selector) || !\is_int($unitsLeft)) {
            throw new \UnexpectedValueException(\sprintf('The low stock event %s has no selector or units left.', $event->id));
        }

        $this->lowStockNotifier->stockRunningLow($selector, $unitsLeft);
    }
}
