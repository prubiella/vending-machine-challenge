<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\Infrastructure\Outbox\SpyLowStockNotifier;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Infrastructure\Console\VendingOutboxRelayCommand;
use App\VendingMachine\Infrastructure\History\DbalMachineHistory;
use App\VendingMachine\Infrastructure\Outbox\MachineOutbox;
use App\VendingMachine\Infrastructure\Outbox\OutboxEvent;
use App\VendingMachine\Infrastructure\Outbox\OutboxRelay;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Output\BufferedOutput;
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

    public function testWatchRelaysOnePassThenStopsWhenSigtermWasAlreadyReceived(): void
    {
        $command = self::getContainer()->get(VendingOutboxRelayCommand::class);
        $commandBus = self::getContainer()->get(CommandBus::class);
        $commandBus->dispatch(new InsertCoinCommand(25));
        $commandBus->dispatch(new ReturnCoinsCommand());
        $output = new BufferedOutput();

        $command->handleSignal(\SIGTERM);
        $exitCode = $command($output, watch: true);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame("Events relayed to the history: 1.\nStopped watching the outbox.\n", $output->fetch());
    }

    public function testWatchKeepsPassingAndSaysNothingOnAPassWithNothingToRelay(): void
    {
        $outbox = new class implements MachineOutbox {
            public int $passes = 0;
            public ?VendingOutboxRelayCommand $stopsOnThirdPass = null;

            public function pending(): array
            {
                ++$this->passes;
                if (3 === $this->passes) {
                    $this->stopsOnThirdPass?->handleSignal(\SIGTERM);
                }

                return 2 === $this->passes ? [new OutboxEvent('00000000-0000-7000-8000-000000000001', 'credit_returned', new \DateTimeImmutable(), ['coins' => []])] : [];
            }

            public function remove(array $ids): void
            {
            }
        };
        $relay = new OutboxRelay($outbox, self::getContainer()->get(DbalMachineHistory::class), new SpyLowStockNotifier());
        $command = new VendingOutboxRelayCommand($relay, secondsBetweenPasses: 0);
        $outbox->stopsOnThirdPass = $command;
        $output = new BufferedOutput();

        $command($output, watch: true);

        self::assertSame(3, $outbox->passes);
        self::assertSame("Events relayed to the history: 1.\nStopped watching the outbox.\n", $output->fetch());
    }

    public function testAPassWithNothingPendingStillSaysSo(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('vending:outbox:relay'));

        $tester->execute([]);

        self::assertSame("Events relayed to the history: 0.\n", $tester->getDisplay(true));
    }

    public function testTheConsoleForwardsTheSignalsThatStopAContainerOrATerminal(): void
    {
        $found = new Application(self::bootKernel())->find('vending:outbox:relay');
        $command = $found instanceof LazyCommand ? $found->getCommand() : $found;

        self::assertSame([\SIGTERM, \SIGINT], $command->getSubscribedSignals());
        self::assertFalse($command->handleSignal(\SIGTERM));
    }
}
