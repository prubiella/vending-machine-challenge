<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\DbalDatabaseProbe;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class DbalDatabaseProbeTest extends TestCase
{
    public function testADatabaseThatAnswersIsReachable(): void
    {
        $probe = new DbalDatabaseProbe(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

        self::assertTrue($probe->isReachable());
    }

    public function testADatabaseThatCannotBeOpenedIsNotReachable(): void
    {
        $probe = new DbalDatabaseProbe(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => '/no/such/folder/database.sqlite']));

        self::assertFalse($probe->isReachable());
    }
}
