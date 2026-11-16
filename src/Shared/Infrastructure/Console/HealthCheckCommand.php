<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Application\Bus\QueryBus;
use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use App\Shared\Application\Query\HealthCheck\HealthCheckResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:health-check', description: 'Check that the application is up and report each of its dependencies')]
final readonly class HealthCheckCommand
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option('Fail when a dependency is ko, as a readiness check')]
        bool $ready = false,
    ): int {
        $health = $this->queryBus->query(new HealthCheckQuery());
        \assert($health instanceof HealthCheckResponse);

        $output->writeln(json_encode($health, \JSON_THROW_ON_ERROR));

        $notReady = \in_array(HealthCheckResponse::KO, $health->checks, true);

        return $ready && $notReady ? Command::FAILURE : Command::SUCCESS;
    }
}
