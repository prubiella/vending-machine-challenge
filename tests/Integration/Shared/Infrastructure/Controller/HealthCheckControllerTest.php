<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Controller;

use App\Shared\Application\Query\HealthCheck\DatabaseProbe;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HealthCheckControllerTest extends KernelTestCase
{
    public function testAnswersTheHealthCheckRouteWithTheQueryResult(): void
    {
        $response = self::bootKernel()->handle(Request::create('/health-check'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status":"ok","database":"ok"}', $response->getContent());
    }

    public function testAnswersServiceUnavailableWhenTheDatabaseIsNotReachable(): void
    {
        $kernel = self::bootKernel();
        self::getContainer()->set(DatabaseProbe::class, new class implements DatabaseProbe {
            public function isReachable(): bool
            {
                return false;
            }
        });

        $response = $kernel->handle(Request::create('/health-check'));

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('{"status":"error","database":"error"}', $response->getContent());
    }
}
