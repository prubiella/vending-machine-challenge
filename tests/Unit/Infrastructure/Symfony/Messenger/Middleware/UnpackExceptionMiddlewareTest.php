<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Symfony\Messenger\Middleware;

use App\Infrastructure\Symfony\Messenger\Middleware\UnpackExceptionMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;

final class UnpackExceptionMiddlewareTest extends TestCase
{
    public function testRethrowsTheExceptionThrownByTheHandler(): void
    {
        $envelope = new Envelope(new \stdClass());
        $original = new \DomainException('Sold out');
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

    public function testPassesTheEnvelopeThroughWhenTheHandlerSucceeds(): void
    {
        $envelope = new Envelope(new \stdClass());

        $result = new UnpackExceptionMiddleware()->handle($envelope, new StackMiddleware());

        self::assertSame($envelope, $result);
    }
}
