<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\VendingMachine\Infrastructure\Outbox\OutboxRelay;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'vending:outbox:relay',
    description: 'Copy the events waiting in the machine\'s outbox to the history in MySQL',
)]
final class VendingOutboxRelayCommand implements SignalableCommandInterface
{
    private bool $stopping = false;

    public function __construct(
        private readonly OutboxRelay $relay,
        private readonly int $secondsBetweenPasses = 2,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option('Keep relaying, a pass every few seconds, until SIGTERM or Ctrl+C')]
        bool $watch = false,
    ): int {
        if ($watch) {
            $this->watchUntilStopped($output);
        } else {
            $output->writeln(\sprintf('Events relayed to the history: %d.', $this->relay->relay()));
        }

        return Command::SUCCESS;
    }

    private function watchUntilStopped(OutputInterface $output): void
    {
        do {
            $relayed = $this->relay->relay();
            if ($relayed > 0) {
                $output->writeln(\sprintf('Events relayed to the history: %d.', $relayed));
            }
            if (!$this->stopping) {
                // A signal ends the pause early; the pass in progress always finishes.
                sleep($this->secondsBetweenPasses);
            }
        } while (!$this->stopping);

        $output->writeln('Stopped watching the outbox.');
    }

    /**
     * @return list<int>
     */
    public function getSubscribedSignals(): array
    {
        return [\SIGTERM, \SIGINT];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->stopping = true;

        return false;
    }
}
