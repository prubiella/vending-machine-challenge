<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\VendingMachine\Infrastructure\Persistence\DbalVendingMachineRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'vending:reset',
    description: 'Put the default machine back: the configured products, stock and change, no credit and an empty tray',
)]
final readonly class VendingResetCommand
{
    public function __construct(
        private DbalVendingMachineRepository $repository,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $this->repository->reset();
        $output->writeln('The machine is back to its default products, stock and change.');

        return Command::SUCCESS;
    }
}
