<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Notification;

use App\VendingMachine\Infrastructure\Notification\LoggingLowStockNotifier;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class LoggingLowStockNotifierTest extends TestCase
{
    public function testWarnsWhichProductIsRunningLowAndHowManyUnitsAreLeft(): void
    {
        $logs = new TestHandler();

        new LoggingLowStockNotifier(new Logger('test', [$logs]))->stockRunningLow('WATER', 5);

        self::assertCount(1, $logs->getRecords());
        $record = $logs->getRecords()[0];
        self::assertSame(Level::Warning, $record->level);
        self::assertSame('WATER is running low: 5 units left.', $record->message);
        self::assertSame(['selector' => 'WATER', 'units_left' => 5], $record->context);
    }
}
