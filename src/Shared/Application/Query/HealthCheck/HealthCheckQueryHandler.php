<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\HealthCheck;

final readonly class HealthCheckQueryHandler
{
    public function __construct(
        private DatabaseProbe $databaseProbe,
        private DocumentStoreProbe $documentStoreProbe,
        private TokenKeysProbe $tokenKeysProbe,
    ) {
    }

    public function __invoke(HealthCheckQuery $query): HealthCheckResponse
    {
        return new HealthCheckResponse(HealthCheckResponse::OK, [
            'database' => self::statusOf($this->databaseProbe->isReachable()),
            'document_store' => self::statusOf($this->documentStoreProbe->isReachable()),
            'jwt_keys' => self::statusOf($this->tokenKeysProbe->isUsable()),
        ]);
    }

    /**
     * @return HealthCheckResponse::OK|HealthCheckResponse::KO
     */
    private static function statusOf(bool $answers): string
    {
        return $answers ? HealthCheckResponse::OK : HealthCheckResponse::KO;
    }
}
