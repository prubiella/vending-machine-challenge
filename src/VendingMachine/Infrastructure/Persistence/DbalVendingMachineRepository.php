<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence;

use App\Shared\Application\Persistence\ConcurrentUpdateException;
use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\VendingMachineRepository;
use App\VendingMachine\Infrastructure\Outbox\MachineOutbox;
use App\VendingMachine\Infrastructure\Outbox\OutboxEventFactory;
use App\VendingMachine\Infrastructure\Outbox\OutboxEventMapper;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * The machine as a row of vending_machine, and the events of each save as rows of
 * vending_machine_outbox written in the same transaction (ADR 20).
 *
 * @phpstan-import-type MachineRow from VendingMachineMapper
 */
final readonly class DbalVendingMachineRepository implements VendingMachineRepository, StoredMachineResetter, MachineOutbox
{
    private const string OUTBOX = 'vending_machine_outbox';
    private const int NOT_STORED = 0;
    private const string AGGREGATE = 'vending machine';

    /** @var \WeakMap<VendingMachine, int> the version each machine was loaded or last saved at */
    private \WeakMap $versions;

    public function __construct(
        private Connection $connection,
        private VendingMachineMapper $mapper,
        private DefaultVendingMachineFactory $defaultMachineFactory,
        private MachineId $machineId,
        private OutboxEventFactory $outboxEventFactory,
        private OutboxEventMapper $outboxEventMapper,
    ) {
        $this->versions = new \WeakMap();
    }

    public function get(): VendingMachine
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, version, inventory, wallet_credit, wallet_stock, wallet_cash_box, tray FROM vending_machine WHERE id = ?',
            [$this->machineId->value()],
        );

        if (false === $row) {
            return $this->tracked($this->defaultMachineFactory->create(), self::NOT_STORED);
        }

        return $this->tracked($this->mapper->fromRow($row), self::version($row));
    }

    public function save(VendingMachine $machine): void
    {
        $loadedVersion = $this->versions[$machine] ?? self::NOT_STORED;
        $row = $this->mapper->toRow($machine);
        $events = $this->outboxEventFactory->releasedFrom($machine);

        $this->connection->transactional(function () use ($row, $loadedVersion, $events): void {
            if (self::NOT_STORED === $loadedVersion) {
                $this->insert($row);
            } else {
                $this->update($row, $loadedVersion);
            }
            foreach ($events as $event) {
                $this->connection->insert(self::OUTBOX, ['machine_id' => $row['id'], ...$this->outboxEventMapper->toRow($event)]);
            }
        });

        $this->versions[$machine] = $loadedVersion + 1;
    }

    public function pending(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, type, occurred_at, payload FROM vending_machine_outbox WHERE machine_id = ? ORDER BY occurred_at, id',
            [$this->machineId->value()],
        );

        return array_map($this->outboxEventMapper->fromRow(...), $rows);
    }

    public function remove(array $ids): void
    {
        if ([] === $ids) {
            return;
        }
        $this->connection->executeStatement(
            'DELETE FROM vending_machine_outbox WHERE id IN (?)',
            [$ids],
            [ArrayParameterType::STRING],
        );
    }

    public function reset(): void
    {
        $this->connection->transactional(function (): void {
            $this->connection->delete(self::OUTBOX, ['machine_id' => $this->machineId->value()]);
            $this->connection->delete('vending_machine', ['id' => $this->machineId->value()]);
        });
    }

    /**
     * @param MachineRow $row
     */
    private function insert(array $row): void
    {
        try {
            $this->connection->insert('vending_machine', ['version' => 1, ...$row]);
        } catch (UniqueConstraintViolationException) {
            throw ConcurrentUpdateException::sinceLoaded(self::AGGREGATE, self::NOT_STORED);
        }
    }

    /**
     * @param MachineRow $row
     */
    private function update(array $row, int $loadedVersion): void
    {
        $updated = $this->connection->update(
            'vending_machine',
            ['version' => $loadedVersion + 1, ...$row],
            ['id' => $row['id'], 'version' => $loadedVersion],
        );

        if (0 === $updated) {
            throw ConcurrentUpdateException::sinceLoaded(self::AGGREGATE, $loadedVersion);
        }
    }

    private function tracked(VendingMachine $machine, int $version): VendingMachine
    {
        $this->versions[$machine] = $version;

        return $machine;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function version(array $row): int
    {
        if (!is_numeric($row['version'] ?? null)) {
            throw new \UnexpectedValueException('The vending_machine row has no version.');
        }

        return (int) $row['version'];
    }
}
