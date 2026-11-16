<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Domain\Coin\UnknownCoinException;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LoggingCommandMiddlewareTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testTheCommandBusLogsEveryCommandItHandles(): void
    {
        $logs = self::appLogs();

        self::getContainer()->get(CommandBus::class)->dispatch(new InsertCoinCommand(100));

        self::assertTrue($logs->hasInfo('Command InsertCoinCommand handled.'));
    }

    public function testTheCommandBusLogsTheRefusalOfTheDomainAsItIs(): void
    {
        $logs = self::appLogs();

        try {
            self::getContainer()->get(CommandBus::class)->dispatch(new InsertCoinCommand(50));
            self::fail('Expected an UnknownCoinException.');
        } catch (UnknownCoinException) {
            self::assertTrue($logs->hasNoticeThatContains('Command InsertCoinCommand refused:'));
        }
    }

    private static function appLogs(): TestHandler
    {
        $logger = self::getContainer()->get(LoggerInterface::class);
        $logger->pushHandler($logs = new TestHandler());

        return $logs;
    }
}
