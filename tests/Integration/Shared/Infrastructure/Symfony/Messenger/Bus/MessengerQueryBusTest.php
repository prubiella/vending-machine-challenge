<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Symfony\Messenger\Bus;

use App\Shared\Application\Bus\QueryBus;
use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use App\Shared\Application\Query\HealthCheck\HealthCheckResponse;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MessengerQueryBusTest extends KernelTestCase
{
    public function testRoutesAQueryToItsHandlerAndReturnsTheResult(): void
    {
        $bus = self::getContainer()->get(QueryBus::class);

        self::assertEquals(new HealthCheckResponse(HealthCheckResponse::OK, ['database' => HealthCheckResponse::OK, 'document_store' => HealthCheckResponse::OK]), $bus->query(new HealthCheckQuery()));
    }
}
