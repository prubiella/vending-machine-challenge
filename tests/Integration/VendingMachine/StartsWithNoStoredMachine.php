<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Before;

/**
 * For a KernelTestCase: each test starts from the default machine, as no machine is stored.
 */
trait StartsWithNoStoredMachine
{
    #[Before]
    protected function deleteTheStoredMachine(): void
    {
        self::getContainer()->get(Connection::class)->executeStatement('DELETE FROM vending_machine');
    }
}
