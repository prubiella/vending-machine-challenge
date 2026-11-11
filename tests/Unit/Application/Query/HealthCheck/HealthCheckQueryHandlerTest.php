<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query\HealthCheck;

use App\Application\Query\HealthCheck\HealthCheckQuery;
use App\Application\Query\HealthCheck\HealthCheckQueryHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Kernel;

class HealthCheckQueryHandlerTest extends TestCase
{
    public function testHealthCheckQueryReturnsRuntimeVersions(): void
    {
        $result = (new HealthCheckQueryHandler())(new HealthCheckQuery());

        $this->assertEquals(['php' => \PHP_VERSION, 'symfony' => Kernel::VERSION], $result);
    }
}
