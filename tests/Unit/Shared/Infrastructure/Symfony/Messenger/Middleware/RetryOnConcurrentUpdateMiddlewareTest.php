<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Persistence\ConcurrentUpdateException;
use App\Shared\Infrastructure\Symfony\Messenger\Middleware\RetryOnConcurrentUpdateMiddleware;
use App\Shared\Infrastructure\Symfony\Messenger\Middleware\UnpackExceptionMiddleware;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class RetryOnConcurrentUpdateMiddlewareTest extends TestCase
{
    private int $attempts = 0;
    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
    }

    public function testRunsTheHandlerOnceWhenItSucceeds(): void
    {
        $this->bus($this->handlerFailingWith(0), maxRetries: 3)->dispatch(new \stdClass());

        self::assertSame(1, $this->attempts);
    }

    public function testRunsTheHandlerAgainUntilItSucceeds(): void
    {
        $this->bus($this->handlerFailingWith(2), maxRetries: 3)->dispatch(new \stdClass());

        self::assertSame(3, $this->attempts);
    }

    public function testGivesUpWithTheConcurrentUpdateAfterTheLastRetry(): void
    {
        try {
            $this->bus($this->handlerFailingWith(5), maxRetries: 2)->dispatch(new \stdClass());
            self::fail('Expected a ConcurrentUpdateException.');
        } catch (ConcurrentUpdateException $e) {
            self::assertSame(3, $this->attempts);
            self::assertSame('The thing was changed by another request since it was loaded at version 3. The request can be retried.', $e->getMessage());
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
            $this->bus($handler, maxRetries: 3)->dispatch(new \stdClass());
            self::fail('Expected the handler failure.');
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e);
            self::assertSame(1, $this->attempts);
        }
    }

    public function testLogsEachRetryAtNoticeWithTheCommandAndItsData(): void
    {
        $command = new \stdClass();
        $command->cents = 25;

        $this->bus($this->handlerFailingWith(2), maxRetries: 3)->dispatch($command);

        $context = ['command' => \stdClass::class, 'data' => ['cents' => 25]];
        self::assertSame([
            [Level::Notice, 'Command stdClass met a concurrent update: retry 1 of 3.', $context],
            [Level::Notice, 'Command stdClass met a concurrent update: retry 2 of 3.', $context],
        ], array_values(array_map(static fn (LogRecord $record): array => [$record->level, $record->message, $record->context], $this->logs->getRecords())));
    }

    public function testLogsEachRetryWithTheSensitiveFieldsOfTheCommandMasked(): void
    {
        $this->bus($this->handlerFailingWith(1), maxRetries: 3)->dispatch(new LogInCommand('pau@example.com', 'correct horse', 'T-1'));

        $context = ['command' => LogInCommand::class, 'data' => ['email' => 'pau***', 'password' => '***', 'terminal' => 'T-1']];
        self::assertSame([
            [Level::Notice, 'Command LogInCommand met a concurrent update: retry 1 of 3.', $context],
        ], array_values(array_map(static fn (LogRecord $record): array => [$record->level, $record->message, $record->context], $this->logs->getRecords())));
    }

    private function handlerFailingWith(int $concurrentUpdates): \Closure
    {
        return function () use ($concurrentUpdates): void {
            if (++$this->attempts <= $concurrentUpdates) {
                throw ConcurrentUpdateException::sinceLoaded('thing', $this->attempts);
            }
        };
    }

    private function bus(\Closure $handler, int $maxRetries): MessageBus
    {
        return new MessageBus([
            new UnpackExceptionMiddleware(),
            new RetryOnConcurrentUpdateMiddleware($maxRetries, new Logger('test', [$this->logs])),
            new HandleMessageMiddleware(new HandlersLocator([\stdClass::class => [$handler], LogInCommand::class => [$handler]])),
        ]);
    }
}
