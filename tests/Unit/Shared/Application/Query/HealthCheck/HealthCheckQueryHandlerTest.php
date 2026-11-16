<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Query\HealthCheck;

use App\Shared\Application\Query\HealthCheck\DatabaseProbe;
use App\Shared\Application\Query\HealthCheck\DocumentStoreProbe;
use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use App\Shared\Application\Query\HealthCheck\HealthCheckQueryHandler;
use App\Shared\Application\Query\HealthCheck\HealthCheckResponse;
use App\Shared\Application\Query\HealthCheck\TokenKeysProbe;
use PHPUnit\Framework\TestCase;

final class HealthCheckQueryHandlerTest extends TestCase
{
    public function testReportsEveryDependencyOkWhenEveryOneIsReachable(): void
    {
        $handler = new HealthCheckQueryHandler(self::databaseProbe(reachable: true), self::documentStoreProbe(reachable: true), self::tokenKeysProbe(usable: true));

        $health = $handler(new HealthCheckQuery());

        self::assertEquals(new HealthCheckResponse(HealthCheckResponse::OK, [
            'database' => HealthCheckResponse::OK,
            'document_store' => HealthCheckResponse::OK,
            'jwt_keys' => HealthCheckResponse::OK,
        ]), $health);
    }

    public function testReportsTheDatabaseKoWhileTheApplicationStaysOk(): void
    {
        $handler = new HealthCheckQueryHandler(self::databaseProbe(reachable: false), self::documentStoreProbe(reachable: true), self::tokenKeysProbe(usable: true));

        $health = $handler(new HealthCheckQuery());

        self::assertEquals(new HealthCheckResponse(HealthCheckResponse::OK, [
            'database' => HealthCheckResponse::KO,
            'document_store' => HealthCheckResponse::OK,
            'jwt_keys' => HealthCheckResponse::OK,
        ]), $health);
    }

    public function testReportsTheDocumentStoreKoWhileTheApplicationStaysOk(): void
    {
        $handler = new HealthCheckQueryHandler(self::databaseProbe(reachable: true), self::documentStoreProbe(reachable: false), self::tokenKeysProbe(usable: true));

        $health = $handler(new HealthCheckQuery());

        self::assertEquals(new HealthCheckResponse(HealthCheckResponse::OK, [
            'database' => HealthCheckResponse::OK,
            'document_store' => HealthCheckResponse::KO,
            'jwt_keys' => HealthCheckResponse::OK,
        ]), $health);
    }

    public function testReportsTheJwtKeysKoWhileTheApplicationStaysOk(): void
    {
        $handler = new HealthCheckQueryHandler(self::databaseProbe(reachable: true), self::documentStoreProbe(reachable: true), self::tokenKeysProbe(usable: false));

        $health = $handler(new HealthCheckQuery());

        self::assertEquals(new HealthCheckResponse(HealthCheckResponse::OK, [
            'database' => HealthCheckResponse::OK,
            'document_store' => HealthCheckResponse::OK,
            'jwt_keys' => HealthCheckResponse::KO,
        ]), $health);
    }

    private static function databaseProbe(bool $reachable): DatabaseProbe
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

    private static function documentStoreProbe(bool $reachable): DocumentStoreProbe
    {
        return new readonly class($reachable) implements DocumentStoreProbe {
            public function __construct(private bool $reachable)
            {
            }

            public function isReachable(): bool
            {
                return $this->reachable;
            }
        };
    }

    private static function tokenKeysProbe(bool $usable): TokenKeysProbe
    {
        return new readonly class($usable) implements TokenKeysProbe {
            public function __construct(private bool $usable)
            {
            }

            public function isUsable(): bool
            {
                return $this->usable;
            }
        };
    }
}
