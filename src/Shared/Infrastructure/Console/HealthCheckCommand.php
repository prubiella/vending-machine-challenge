<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Application\Bus\QueryBus;
use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use App\Shared\Application\Query\HealthCheck\HealthCheckResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:health-check', description: 'Check that the application and its database are up')]
final readonly class HealthCheckCommand
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $health = $this->queryBus->query(new HealthCheckQuery());
        \assert($health instanceof HealthCheckResponse);

        $output->writeln(json_encode($health, \JSON_THROW_ON_ERROR));

        return HealthCheckResponse::OK === $health->status ? Command::SUCCESS : Command::FAILURE;
    }
}
