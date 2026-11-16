<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Controller;

use App\Shared\Application\Query\HealthCheck\DatabaseProbe;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

final class HealthCheckControllerTest extends KernelTestCase
{
    public function testAnswersTheHealthCheckRouteWithTheDetailOfEachDependency(): void
    {
        $response = self::bootKernel()->handle(Request::create('/health-check'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status":"ok","checks":{"database":"ok"}}', $response->getContent());
    }

    public function testTheApplicationStaysAliveWhenTheDatabaseIsNotReachable(): void
    {
        $response = self::kernelWithUnreachableDatabase()->handle(Request::create('/health-check'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status":"ok","checks":{"database":"ko"}}', $response->getContent());
    }

    public function testTheApplicationIsReadyWhenEveryDependencyIsOk(): void
    {
        $response = self::bootKernel()->handle(Request::create('/health-check/ready'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status":"ok","checks":{"database":"ok"}}', $response->getContent());
    }

    public function testTheApplicationIsNotReadyWhenTheDatabaseIsNotReachable(): void
    {
        $response = self::kernelWithUnreachableDatabase()->handle(Request::create('/health-check/ready'));

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('{"status":"ok","checks":{"database":"ko"}}', $response->getContent());
    }

    private static function kernelWithUnreachableDatabase(): KernelInterface
    {
        $kernel = self::bootKernel();
        self::getContainer()->set(DatabaseProbe::class, new class implements DatabaseProbe {
            public function isReachable(): bool
            {
                return false;
            }
        });

        return $kernel;
    }
}
