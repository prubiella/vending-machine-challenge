<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Infrastructure\Console\VendingOutboxRelayCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class VendingOutboxRelayCommandTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    private const int SIGINT = 2;
    private const int SIGTERM = 15;

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

    public function testStopsWatchingAfterThePassItIsInWhenAskedToStop(): void
    {
        $command = self::getContainer()->get(VendingOutboxRelayCommand::class);
        $commandBus = self::getContainer()->get(CommandBus::class);
        $commandBus->dispatch(new InsertCoinCommand(25));
        $commandBus->dispatch(new ReturnCoinsCommand());
        $output = new BufferedOutput();

        $command->handleSignal(self::SIGTERM);
        $exitCode = $command($output, watch: true);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame("Events relayed to the history: 1.\nStopped watching the outbox.\n", $output->fetch());
    }

    public function testListensForTheSignalsThatStopAContainerOrATerminal(): void
    {
        $command = self::getContainer()->get(VendingOutboxRelayCommand::class);

        self::assertSame([self::SIGTERM, self::SIGINT], $command->getSubscribedSignals());
    }
}
