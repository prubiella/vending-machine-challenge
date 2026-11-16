<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Before;

/**
 * For a KernelTestCase: each test starts with no technician stored.
 */
trait StartsWithNoTechnicians
{
    #[Before]
    protected function deleteTheStoredTechnicians(): void
    {
        self::getContainer()->get(Connection::class)->executeStatement('DELETE FROM technicians');
    }
}
