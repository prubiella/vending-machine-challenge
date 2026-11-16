<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Monolog;

use App\Shared\Infrastructure\Symfony\Monolog\PasswordOptionMaskingProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordOptionMaskingProcessorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function commands(): iterable
    {
        yield 'a plain value' => ['vending:technician:token --username=ana --password=correct-horse -n', 'vending:technician:token --username=ana --password=*** -n'];
        yield 'a quoted value with spaces' => ["vending:service --password='correct horse' --units=WATER:2", 'vending:service --password=*** --units=WATER:2'];
        yield 'a quoted value with an escaped quote' => ["vending:service --password='it'\\''s' -n", 'vending:service --password=*** -n'];
        yield 'a double-quoted value with escaped quotes' => ['vending:service --password="a \\"b\\" c" -n', 'vending:service --password=*** -n'];
        yield 'a value at the end of the command' => ['vending:technician:token --username=ana --password=secret', 'vending:technician:token --username=ana --password=***'];
        yield 'a value after a space' => ['vending:service --password correct-horse -n', 'vending:service --password=*** -n'];
        yield 'no password' => ['vending:run 1,GET-WATER', 'vending:run 1,GET-WATER'];
    }

    #[DataProvider('commands')]
    public function testHidesThePasswordOptionOfTheLoggedCommand(string $command, string $logged): void
    {
        $record = new PasswordOptionMaskingProcessor()(self::record(['command' => $command, 'code' => 1]));

        self::assertSame(['command' => $logged, 'code' => 1], $record->context);
    }

    public function testLeavesARecordWithoutACommandAsItIs(): void
    {
        $record = self::record(['cents' => 25]);

        self::assertSame($record, new PasswordOptionMaskingProcessor()($record));
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function record(array $context): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable(), 'console', Level::Debug, 'Command "{command}" exited with code "{code}"', $context);
    }
}
