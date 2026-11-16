<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\HealthCheck;

final readonly class HealthCheckQueryHandler
{
    public function __construct(
        private DatabaseProbe $databaseProbe,
        private DocumentStoreProbe $documentStoreProbe,
    ) {
    }

    public function __invoke(HealthCheckQuery $query): HealthCheckResponse
    {
        return new HealthCheckResponse(HealthCheckResponse::OK, [
            'database' => self::statusOf($this->databaseProbe->isReachable()),
            'document_store' => self::statusOf($this->documentStoreProbe->isReachable()),
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
