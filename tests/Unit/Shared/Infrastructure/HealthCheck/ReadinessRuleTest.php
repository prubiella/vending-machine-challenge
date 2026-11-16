<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\HealthCheck;

use App\Shared\Application\Query\HealthCheck\HealthCheckResponse;
use App\Shared\Infrastructure\HealthCheck\ReadinessRule;
use PHPUnit\Framework\TestCase;

final class ReadinessRuleTest extends TestCase
{
    public function testIsReadyWhenEveryDependencyIsOk(): void
    {
        $health = new HealthCheckResponse(HealthCheckResponse::OK, ['database' => HealthCheckResponse::OK]);

        self::assertTrue(ReadinessRule::isReady($health));
    }

    public function testIsNotReadyWhenAnyDependencyIsKo(): void
    {
        $health = new HealthCheckResponse(HealthCheckResponse::OK, ['database' => HealthCheckResponse::KO]);

        self::assertFalse(ReadinessRule::isReady($health));
    }
}
