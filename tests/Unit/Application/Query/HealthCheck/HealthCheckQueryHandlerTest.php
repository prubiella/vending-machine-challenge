<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query\HealthCheck;

use App\Application\Query\HealthCheck\HealthCheckQuery;
use App\Application\Query\HealthCheck\HealthCheckQueryHandler;
use PHPUnit\Framework\TestCase;

final class HealthCheckQueryHandlerTest extends TestCase
{
    public function testReportsTheServiceAsUp(): void
    {
        $result = new HealthCheckQueryHandler()(new HealthCheckQuery());

        self::assertSame(['status' => 'ok'], $result);
    }
}
