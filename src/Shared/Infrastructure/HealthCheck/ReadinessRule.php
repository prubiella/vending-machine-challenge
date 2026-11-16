<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\HealthCheck;

use App\Shared\Application\Query\HealthCheck\HealthCheckResponse;

final class ReadinessRule
{
    public static function isReady(HealthCheckResponse $health): bool
    {
        return !\in_array(HealthCheckResponse::KO, $health->checks, true);
    }
}
