<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\DbalDatabaseProbe;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class DbalDatabaseProbeTest extends TestCase
{
    public function testADatabaseThatAnswersIsReachable(): void
    {
        $probe = new DbalDatabaseProbe(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), new NullLogger());

        self::assertTrue($probe->isReachable());
    }

    public function testADatabaseThatCannotBeOpenedIsNotReachable(): void
    {
        $probe = self::probeOfADatabaseThatCannotBeOpened(new NullLogger());

        self::assertFalse($probe->isReachable());
    }

    public function testLogsWhyTheDatabaseIsNotReachable(): void
    {
        $logs = new TestHandler();
        $probe = self::probeOfADatabaseThatCannotBeOpened(new Logger('test', [$logs]));

        $probe->isReachable();

        self::assertTrue($logs->hasWarningThatPasses(
            static fn (LogRecord $record): bool => $record->context['exception'] instanceof DbalException,
        ));
    }

    private static function probeOfADatabaseThatCannotBeOpened(LoggerInterface $logger): DbalDatabaseProbe
    {
        return new DbalDatabaseProbe(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => '/no/such/folder/database.sqlite']), $logger);
    }
}
