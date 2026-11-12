<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\HealthCheck;

final readonly class HealthCheckQueryHandler
{
    /**
     * @return array{status: 'ok'}
     */
    public function __invoke(HealthCheckQuery $query): array
    {
        return ['status' => 'ok'];
    }
}
