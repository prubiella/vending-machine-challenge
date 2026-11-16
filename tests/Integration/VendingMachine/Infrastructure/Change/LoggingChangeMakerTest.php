<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Change;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LoggingChangeMakerTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testASaleLogsTheChangeItGives(): void
    {
        $container = self::getContainer();
        $container->get(LoggerInterface::class)->pushHandler($logs = new TestHandler());
        $commandBus = $container->get(CommandBus::class);

        $commandBus->dispatch(new InsertCoinCommand(100));
        $commandBus->dispatch(new SelectProductCommand('WATER'));

        self::assertTrue($logs->hasInfo('Change of 35 cents made.'));
    }
}
