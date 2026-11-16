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
}
