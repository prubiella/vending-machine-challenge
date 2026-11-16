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
        return new HealthCheckResponse(HealthCheckResponse::OK, [
            'database' => self::statusOf($this->databaseProbe->isReachable()),
        ]);
    }

    /**
     * @return HealthCheckResponse::OK|HealthCheckResponse::KO
     */
    private static function statusOf(bool $reachable): string
    {
        return $reachable ? HealthCheckResponse::OK : HealthCheckResponse::KO;
    }
}
