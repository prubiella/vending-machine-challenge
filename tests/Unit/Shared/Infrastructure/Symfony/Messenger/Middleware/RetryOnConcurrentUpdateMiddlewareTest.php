<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Persistence\ConcurrentUpdateException;
use App\Shared\Infrastructure\Symfony\Messenger\Middleware\RetryOnConcurrentUpdateMiddleware;
use App\Shared\Infrastructure\Symfony\Messenger\Middleware\UnpackExceptionMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class RetryOnConcurrentUpdateMiddlewareTest extends TestCase
{
    private int $attempts = 0;

    public function testRunsTheHandlerOnceWhenItSucceeds(): void
    {
        self::bus($this->handlerFailingWith(0), maxRetries: 3)->dispatch(new \stdClass());

        self::assertSame(1, $this->attempts);
    }

    public function testRunsTheHandlerAgainUntilItSucceeds(): void
    {
        self::bus($this->handlerFailingWith(2), maxRetries: 3)->dispatch(new \stdClass());

        self::assertSame(3, $this->attempts);
    }

    public function testGivesUpWithTheConcurrentUpdateAfterTheLastRetry(): void
    {
        try {
            self::bus($this->handlerFailingWith(5), maxRetries: 2)->dispatch(new \stdClass());
            self::fail('Expected a ConcurrentUpdateException.');
        } catch (ConcurrentUpdateException) {
            self::assertSame(3, $this->attempts);
        }
    }

    public function testDoesNotRetryAnyOtherFailure(): void
    {
        $failure = new \RuntimeException('Not a concurrent update');
        $handler = function () use ($failure): never {
            ++$this->attempts;

            throw $failure;
        };

        try {
            self::bus($handler, maxRetries: 3)->dispatch(new \stdClass());
            self::fail('Expected the handler failure.');
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e);
            self::assertSame(1, $this->attempts);
        }
    }

    private function handlerFailingWith(int $concurrentUpdates): \Closure
    {
        return function () use ($concurrentUpdates): void {
            if (++$this->attempts <= $concurrentUpdates) {
                throw ConcurrentUpdateException::sinceLoaded('thing', $this->attempts);
            }
        };
    }

    private static function bus(\Closure $handler, int $maxRetries): MessageBus
    {
        return new MessageBus([
            new UnpackExceptionMiddleware(),
            new RetryOnConcurrentUpdateMiddleware($maxRetries),
            new HandleMessageMiddleware(new HandlersLocator([\stdClass::class => [$handler]])),
        ]);
    }
}
