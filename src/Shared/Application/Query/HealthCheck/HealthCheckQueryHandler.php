<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\HealthCheck;

final readonly class HealthCheckQueryHandler
{
    public function __construct(
        private DatabaseProbe $databaseProbe,
    ) {
    }

    public function __invoke(HealthCheckQuery $query): HealthCheckResponse
    {
        $database = $this->databaseProbe->isReachable() ? HealthCheckResponse::OK : HealthCheckResponse::ERROR;

        return new HealthCheckResponse($database, $database);
    }
}
