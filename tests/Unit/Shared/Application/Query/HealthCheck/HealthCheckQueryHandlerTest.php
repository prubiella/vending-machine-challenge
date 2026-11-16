<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Query\HealthCheck;

use App\Shared\Application\Query\HealthCheck\DatabaseProbe;
use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use App\Shared\Application\Query\HealthCheck\HealthCheckQueryHandler;
use App\Shared\Application\Query\HealthCheck\HealthCheckResponse;
use PHPUnit\Framework\TestCase;

final class HealthCheckQueryHandlerTest extends TestCase
{
    public function testReportsEveryDependencyOkWhenTheDatabaseIsReachable(): void
    {
        $handler = new HealthCheckQueryHandler(self::probe(reachable: true));

        $health = $handler(new HealthCheckQuery());

        self::assertEquals(new HealthCheckResponse(HealthCheckResponse::OK, ['database' => HealthCheckResponse::OK]), $health);
    }

    public function testReportsTheDatabaseKoWhileTheApplicationStaysOk(): void
    {
        $handler = new HealthCheckQueryHandler(self::probe(reachable: false));

        $health = $handler(new HealthCheckQuery());

        self::assertEquals(new HealthCheckResponse(HealthCheckResponse::OK, ['database' => HealthCheckResponse::KO]), $health);
    }

    private static function probe(bool $reachable): DatabaseProbe
    {
        return new readonly class($reachable) implements DatabaseProbe {
            public function __construct(private bool $reachable)
            {
            }

            public function isReachable(): bool
            {
                return $this->reachable;
            }
        };
    }
}
