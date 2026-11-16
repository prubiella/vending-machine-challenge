<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\MongoDocumentStoreProbe;
use MongoDB\Client;
use MongoDB\Driver\Exception\Exception as MongoException;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class MongoDocumentStoreProbeTest extends TestCase
{
    public function testADocumentStoreThatDoesNotAnswerIsNotReachable(): void
    {
        $probe = self::probeOfADocumentStoreThatDoesNotAnswer(new NullLogger());

        self::assertFalse($probe->isReachable());
    }

    public function testLogsWhyTheDocumentStoreIsNotReachable(): void
    {
        $logs = new TestHandler();
        $probe = self::probeOfADocumentStoreThatDoesNotAnswer(new Logger('test', [$logs]));

        $probe->isReachable();

        self::assertTrue($logs->hasWarningThatPasses(
            static fn (LogRecord $record): bool => $record->context['exception'] instanceof MongoException,
        ));
    }

    private static function probeOfADocumentStoreThatDoesNotAnswer(LoggerInterface $logger): MongoDocumentStoreProbe
    {
        $client = new Client('mongodb://127.0.0.1:1', ['serverSelectionTimeoutMS' => 100]);

        return new MongoDocumentStoreProbe($client->getDatabase('app'), $logger);
    }
}
