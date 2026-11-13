<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Symfony\Messenger\Bus;

use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use App\Shared\Infrastructure\Symfony\Messenger\Bus\InMemoryQueryBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InMemoryQueryBusTest extends KernelTestCase
{
    public function testRoutesAQueryToItsHandlerAndReturnsTheResult(): void
    {
        $bus = self::getContainer()->get(InMemoryQueryBus::class);

        self::assertSame(['status' => 'ok'], $bus->query(new HealthCheckQuery()));
    }
}
