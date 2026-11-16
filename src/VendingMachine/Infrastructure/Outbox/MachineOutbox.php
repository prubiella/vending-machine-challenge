<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Outbox;

/**
 * The events saved with the machine and not relayed yet. Each adapter of VendingMachineRepository
 * keeps them in its own store, next to the machine.
 */
interface MachineOutbox
{
    /**
     * @return list<OutboxEvent> oldest first
     */
    public function pending(): array;

    /**
     * Forgets the events already relayed. It does not count as a change of the machine, so it
     * never makes a save of the machine conflict.
     *
     * @param list<string> $ids
     */
    public function remove(array $ids): void;
}
