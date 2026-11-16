<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence;

/**
 * Forgets the stored machine, so the next load gives the default machine. It is not part of the
 * domain's repository: only operators reset a machine, from the console.
 */
interface StoredMachineResetter
{
    public function reset(): void;
}
