<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Query\HealthCheck\HealthCheckQuery;
use App\Infrastructure\Symfony\Messenger\Bus\InMemoryQueryBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:health-check', description: 'Check that the application is up')]
final readonly class HealthCheckCommand
{
    public function __construct(
        private InMemoryQueryBus $queryBus,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln(json_encode($this->queryBus->query(new HealthCheckQuery()), \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
