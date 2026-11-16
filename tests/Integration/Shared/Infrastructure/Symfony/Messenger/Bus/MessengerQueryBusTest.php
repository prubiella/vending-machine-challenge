<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Symfony\Messenger\Bus;

use App\Shared\Application\Bus\QueryBus;
use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MessengerQueryBusTest extends KernelTestCase
{
    public function testRoutesAQueryToItsHandlerAndReturnsTheResult(): void
    {
        $bus = self::getContainer()->get(QueryBus::class);

        self::assertSame(['status' => 'ok'], $bus->query(new HealthCheckQuery()));
    }
}
