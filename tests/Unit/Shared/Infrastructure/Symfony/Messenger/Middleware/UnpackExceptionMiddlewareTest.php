<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Infrastructure\Symfony\Messenger\Middleware\UnpackExceptionMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;

final class UnpackExceptionMiddlewareTest extends TestCase
{
    public function testRethrowsTheExceptionThrownByTheHandler(): void
    {
        $envelope = new Envelope(new \stdClass());
        $original = new \RuntimeException('Handler failed');
        $failingHandler = new class($envelope, $original) implements MiddlewareInterface {
            public function __construct(private readonly Envelope $envelope, private readonly \Throwable $original)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                throw new HandlerFailedException($this->envelope, [$this->original]);
            }
        };

        $this->expectExceptionObject($original);

        new UnpackExceptionMiddleware()->handle($envelope, new StackMiddleware($failingHandler));
    }

    public function testRethrowsTheHandlerFailedExceptionWhenSeveralHandlersFail(): void
    {
        $first = new \RuntimeException('First handler failed');
        $second = new \RuntimeException('Second handler failed');
        $bus = new MessageBus([
            new UnpackExceptionMiddleware(),
            new HandleMessageMiddleware(new HandlersLocator([\stdClass::class => [
                new HandlerDescriptor(static fn () => throw $first, ['alias' => 'first']),
                new HandlerDescriptor(static fn () => throw $second, ['alias' => 'second']),
            ]])),
        ]);

        try {
            $bus->dispatch(new \stdClass());
            self::fail('Expected a HandlerFailedException.');
        } catch (HandlerFailedException $e) {
            self::assertSame([$first, $second], array_values($e->getWrappedExceptions()));
        }
    }

    public function testPassesTheEnvelopeThroughWhenTheHandlerSucceeds(): void
    {
        $envelope = new Envelope(new \stdClass());

        $result = new UnpackExceptionMiddleware()->handle($envelope, new StackMiddleware());

        self::assertSame($envelope, $result);
    }
}
