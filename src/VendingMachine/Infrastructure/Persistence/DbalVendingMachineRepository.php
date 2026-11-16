<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence;

use App\Shared\Application\Persistence\ConcurrentUpdateException;
use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\VendingMachineRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * @phpstan-import-type MachineRow from VendingMachineMapper
 */
final readonly class DbalVendingMachineRepository implements VendingMachineRepository, StoredMachineResetter
{
    private const int NOT_STORED = 0;
    private const string AGGREGATE = 'vending machine';

    /** @var \WeakMap<VendingMachine, int> the version each machine was loaded or last saved at */
    private \WeakMap $versions;

    public function __construct(
        private Connection $connection,
        private VendingMachineMapper $mapper,
        private DefaultVendingMachineFactory $defaultMachineFactory,
        private MachineId $machineId,
    ) {
        $this->versions = new \WeakMap();
    }

    public function get(): VendingMachine
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, version, inventory, wallet_credit, wallet_stock, tray FROM vending_machine WHERE id = ?',
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

        if (self::NOT_STORED === $loadedVersion) {
            $this->insert($row);
        } else {
            $this->update($row, $loadedVersion);
        }

        $this->versions[$machine] = $loadedVersion + 1;
    }

    public function reset(): void
    {
        $this->connection->delete('vending_machine', ['id' => $this->machineId->value()]);
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
