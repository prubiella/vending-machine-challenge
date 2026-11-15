<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Messenger\Bus;

use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use App\Shared\Infrastructure\Symfony\Messenger\Bus\MessengerQueryBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class MessengerQueryBusTest extends TestCase
{
    public function testReturnsTheResultOfTheOnlyHandler(): void
    {
        $bus = $this->queryBusWithHandlers(new HandlerDescriptor(static fn (): string => 'result'));

        self::assertSame('result', $bus->query(new HealthCheckQuery()));
    }

    public function testRejectsAQueryHandledByMoreThanOneHandler(): void
    {
        $bus = $this->queryBusWithHandlers(
            new HandlerDescriptor(static fn (): string => 'first', ['alias' => 'first']),
            new HandlerDescriptor(static fn (): string => 'second', ['alias' => 'second']),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIs(\sprintf('Query "%s" must be handled by exactly one handler, 2 handled it.', HealthCheckQuery::class));

        $bus->query(new HealthCheckQuery());
    }

    private function queryBusWithHandlers(HandlerDescriptor ...$handlers): MessengerQueryBus
    {
        return new MessengerQueryBus(new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([HealthCheckQuery::class => $handlers])),
        ]));
    }
}
