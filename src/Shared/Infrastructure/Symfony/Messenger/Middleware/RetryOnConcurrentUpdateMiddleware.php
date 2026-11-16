<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Persistence\ConcurrentUpdateException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class RetryOnConcurrentUpdateMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxRetries,
        private LoggerInterface $logger,
    ) {
    }

    // The handler loads the aggregate afresh on each attempt. Every attempt goes to the same next
    // middleware, since the first one uses up the rest of the stack: this one must come right
    // before handle_message.
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $next = $stack->next();
        $retriesDone = 0;

        while (true) {
            try {
                return $next->handle($envelope, $stack);
            } catch (HandlerFailedException $failure) {
                if (!self::causedByConcurrentUpdate($failure) || $retriesDone === $this->maxRetries) {
                    throw $failure;
                }
            }

            ++$retriesDone;
            $this->logRetry($envelope, $retriesDone);
        }
    }

    private static function causedByConcurrentUpdate(HandlerFailedException $failure): bool
    {
        return [] !== $failure->getWrappedExceptions(ConcurrentUpdateException::class);
    }

    private function logRetry(Envelope $envelope, int $retriesDone): void
    {
        $command = $envelope->getMessage();
        $name = LoggedCommand::name($command);
        $this->logger->notice(
            \sprintf('Command %s met a concurrent update: retry %d of %d.', $name, $retriesDone, $this->maxRetries),
            LoggedCommand::context($command),
        );
    }
}
