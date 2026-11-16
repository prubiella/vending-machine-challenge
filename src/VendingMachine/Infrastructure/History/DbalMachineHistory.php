<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\History;

use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Infrastructure\Outbox\OutboxEvent;
use App\VendingMachine\Infrastructure\Outbox\OutboxEventMapper;
use Doctrine\DBAL\Connection;

/**
 * What happened to the machine, one row of machine_history per event, for reports in SQL. The
 * event's id is the key, so an event relayed twice is kept once (ADR 20).
 */
final readonly class DbalMachineHistory
{
    public function __construct(
        private Connection $connection,
        private OutboxEventMapper $mapper,
        private MachineId $machineId,
    ) {
    }

    public function record(OutboxEvent $event): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO machine_history (id, machine_id, type, occurred_at, payload)
                VALUES (:id, :machine_id, :type, :occurred_at, :payload)
                ON DUPLICATE KEY UPDATE id = id
                SQL,
            ['machine_id' => $this->machineId->value(), ...$this->mapper->toRow($event)],
        );
    }
}
