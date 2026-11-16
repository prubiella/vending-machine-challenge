<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine;

use App\VendingMachine\Infrastructure\Persistence\StoredMachineResetter;
use PHPUnit\Framework\Attributes\Before;

/**
 * For a KernelTestCase: each test starts from the default machine, as no machine is stored.
 */
trait StartsWithNoStoredMachine
{
    #[Before]
    protected function forgetTheStoredMachine(): void
    {
        self::getContainer()->get(StoredMachineResetter::class)->reset();
    }
}
