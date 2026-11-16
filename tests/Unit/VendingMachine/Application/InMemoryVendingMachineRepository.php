<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application;

use App\VendingMachine\Domain\Event\VendingMachineEvent;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\VendingMachineRepository;
use App\VendingMachine\Infrastructure\Persistence\DefaultVendingMachineFactory;

final class InMemoryVendingMachineRepository implements VendingMachineRepository
{
    private ?VendingMachine $machine = null;

    /** @var list<VendingMachineEvent> */
    private array $savedEvents = [];

    public function __construct(
        private readonly DefaultVendingMachineFactory $defaultMachineFactory,
    ) {
    }

    // Copies in and out, so a change reaches the store only through save(), as with a database.
    // The parts of the machine are immutable, so a shallow clone is enough.
    public function get(): VendingMachine
    {
        $this->machine ??= $this->defaultMachineFactory->create();

        return clone $this->machine;
    }

    // Takes the machine's events as the database adapters do, so they are saved once.
    public function save(VendingMachine $machine): void
    {
        $this->savedEvents = [...$this->savedEvents, ...$machine->releaseEvents()];
        $this->machine = clone $machine;
    }

    /**
     * @return list<VendingMachineEvent>
     */
    public function savedEvents(): array
    {
        return $this->savedEvents;
    }
}
