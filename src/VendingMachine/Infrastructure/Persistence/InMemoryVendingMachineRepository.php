<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence;

use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\VendingMachineRepository;

final class InMemoryVendingMachineRepository implements VendingMachineRepository
{
    private ?VendingMachine $machine = null;

    public function __construct(
        private readonly DefaultVendingMachineFactory $defaultMachine,
    ) {
    }

    // Copies in and out, so a change reaches the store only through save(), as with a database.
    // The parts of the machine are immutable, so a shallow clone is enough.
    public function get(): VendingMachine
    {
        $this->machine ??= $this->defaultMachine->create();

        return clone $this->machine;
    }

    public function save(VendingMachine $machine): void
    {
        $this->machine = clone $machine;
    }
}
