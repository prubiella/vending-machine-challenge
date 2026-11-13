<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HealthCheckControllerTest extends KernelTestCase
{
    public function testAnswersTheHealthCheckRouteWithTheQueryResult(): void
    {
        $response = self::bootKernel()->handle(Request::create('/health-check'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status":"ok"}', $response->getContent());
    }
}
