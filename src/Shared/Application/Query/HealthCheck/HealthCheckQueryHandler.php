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
            'database' => self::check($this->databaseProbe->isReachable()),
        ]);
    }

    /**
     * @return HealthCheckResponse::OK|HealthCheckResponse::KO
     */
    private static function check(bool $up): string
    {
        return $up ? HealthCheckResponse::OK : HealthCheckResponse::KO;
    }
}
