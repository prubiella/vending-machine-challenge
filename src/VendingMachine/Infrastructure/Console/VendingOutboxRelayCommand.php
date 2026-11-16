<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\VendingMachine\Infrastructure\Outbox\OutboxRelay;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'vending:outbox:relay',
    description: 'Copy the events waiting in the machine\'s outbox to the history in MySQL',
)]
final readonly class VendingOutboxRelayCommand
{
    public function __construct(
        private OutboxRelay $relay,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln(\sprintf('Events relayed to the history: %d.', $this->relay->relay()));

        return Command::SUCCESS;
    }
}
