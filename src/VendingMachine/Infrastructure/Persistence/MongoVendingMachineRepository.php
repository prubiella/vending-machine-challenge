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
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * The machine as one document of the vending_machines collection, under its id, with a version for
 * optimistic locking (ADR 11): a write only matches the document at the version it was loaded at.
 * The events of a save go into the document's outbox array in the same write (ADR 20).
 *
 * @phpstan-import-type MachineDocument from VendingMachineMapper
 * @phpstan-import-type OutboxEntry from OutboxEventMapper
 */
final readonly class MongoVendingMachineRepository implements VendingMachineRepository, StoredMachineResetter, MachineOutbox
{
    private const string COLLECTION = 'vending_machines';
    private const int NOT_STORED = 0;
    // The _id the machine was stored under before it had a UUID, removed on reset.
    private const int LEGACY_MACHINE_ID = 1;
    private const int DUPLICATE_KEY = 11000;
    private const string AGGREGATE = 'vending machine';
    private const array AS_ARRAYS = ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']];

    private Collection $machines;

    /** @var \WeakMap<VendingMachine, int> the version each machine was loaded or last saved at */
    private \WeakMap $versions;

    public function __construct(
        Database $database,
        private VendingMachineMapper $mapper,
        private DefaultVendingMachineFactory $defaultMachineFactory,
        private MachineId $machineId,
        private OutboxEventFactory $outboxEventFactory,
        private OutboxEventMapper $outboxEventMapper,
    ) {
        $this->machines = $database->getCollection(self::COLLECTION);
        $this->versions = new \WeakMap();
    }

    public function get(): VendingMachine
    {
        /** @var array<string, mixed>|null $document */
        $document = $this->machines->findOne(['_id' => $this->machineId->value()], self::AS_ARRAYS);

        if (null === $document) {
            return $this->tracked($this->defaultMachineFactory->create(), self::NOT_STORED);
        }

        return $this->tracked($this->mapper->fromDocument($document), self::version($document));
    }

    public function save(VendingMachine $machine): void
    {
        $loadedVersion = $this->versions[$machine] ?? self::NOT_STORED;
        $document = $this->mapper->toDocument($machine);
        $outbox = array_map($this->outboxEventMapper->toDocument(...), $this->outboxEventFactory->releasedFrom($machine));

        if (self::NOT_STORED === $loadedVersion) {
            $this->insert($document, $outbox);
        } else {
            $this->update($document, $loadedVersion, $outbox);
        }

        $this->versions[$machine] = $loadedVersion + 1;
    }

    public function pending(): array
    {
        $document = $this->machines->findOne(['_id' => $this->machineId->value()], ['projection' => ['outbox' => 1], ...self::AS_ARRAYS]);
        $outbox = \is_array($document) ? ($document['outbox'] ?? []) : [];
        if (!\is_array($outbox)) {
            throw new \UnexpectedValueException('The outbox of the stored vending machine is not a list.');
        }

        return array_values(array_map($this->outboxEventMapper->fromDocument(...), $outbox));
    }

    public function remove(array $ids): void
    {
        $this->machines->updateOne(['_id' => $this->machineId->value()], ['$pull' => ['outbox' => ['id' => ['$in' => $ids]]]]);
    }

    public function reset(): void
    {
        $this->machines->deleteMany(['_id' => ['$in' => [$this->machineId->value(), self::LEGACY_MACHINE_ID]]]);
    }

    /**
     * @param MachineDocument   $document
     * @param list<OutboxEntry> $outbox
     */
    private function insert(array $document, array $outbox): void
    {
        try {
            $this->machines->insertOne(['version' => 1, ...$document, 'outbox' => $outbox]);
        } catch (BulkWriteException $exception) {
            if (self::DUPLICATE_KEY !== $exception->getCode()) {
                throw $exception;
            }

            throw ConcurrentUpdateException::sinceLoaded(self::AGGREGATE, self::NOT_STORED);
        }
    }

    /**
     * @param MachineDocument   $document
     * @param list<OutboxEntry> $outbox
     */
    private function update(array $document, int $loadedVersion, array $outbox): void
    {
        $fields = ['version' => $loadedVersion + 1, ...array_diff_key($document, ['_id' => true])];
        $result = $this->machines->updateOne(
            ['_id' => $document['_id'], 'version' => $loadedVersion],
            ['$set' => $fields, '$push' => ['outbox' => ['$each' => $outbox]]],
        );

        if (0 === $result->getMatchedCount()) {
            throw ConcurrentUpdateException::sinceLoaded(self::AGGREGATE, $loadedVersion);
        }
    }

    private function tracked(VendingMachine $machine, int $version): VendingMachine
    {
        $this->versions[$machine] = $version;

        return $machine;
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function version(array $document): int
    {
        if (!\is_int($document['version'] ?? null)) {
            throw new \UnexpectedValueException('The stored vending machine has no version.');
        }

        return $document['version'];
    }
}
