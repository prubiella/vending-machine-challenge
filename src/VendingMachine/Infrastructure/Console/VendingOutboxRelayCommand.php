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
    private const int SECONDS_BETWEEN_PASSES = 2;
    // The values of SIGINT and SIGTERM, whose constants exist only with the pcntl extension.
    private const int SIGINT = 2;
    private const int SIGTERM = 15;

    private bool $stopping = false;

    public function __construct(
        private readonly OutboxRelay $relay,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option('Keep relaying, a pass every few seconds, until SIGTERM or Ctrl+C')]
        bool $watch = false,
    ): int {
        do {
            $relayed = $this->relay->relay();
            if (!$watch || $relayed > 0) {
                $output->writeln(\sprintf('Events relayed to the history: %d.', $relayed));
            }
            if ($watch && !$this->stopping) {
                // A signal ends the pause early; the pass in progress always finishes.
                sleep(self::SECONDS_BETWEEN_PASSES);
            }
        } while ($watch && !$this->stopping);

        if ($watch) {
            $output->writeln('Stopped watching the outbox.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<int>
     */
    public function getSubscribedSignals(): array
    {
        return [self::SIGTERM, self::SIGINT];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->stopping = true;

        return false;
    }
}
