<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class VendingOutboxRelayCommandTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testRelaysThePendingEventsAndSaysHowMany(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('vending:outbox:relay'));
        $commandBus = self::getContainer()->get(CommandBus::class);
        $commandBus->dispatch(new InsertCoinCommand(25));
        $commandBus->dispatch(new ReturnCoinsCommand());

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame("Events relayed to the history: 1.\n", $tester->getDisplay(true));
    }
}
