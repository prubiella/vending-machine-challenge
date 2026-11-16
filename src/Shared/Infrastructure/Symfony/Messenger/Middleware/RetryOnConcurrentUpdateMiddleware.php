<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Persistence\ConcurrentUpdateException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class RetryOnConcurrentUpdateMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxRetries,
    ) {
    }

    // The handler loads the aggregate afresh on each attempt. Every attempt goes to the same next
    // middleware, since the first one uses up the rest of the stack: this one must come right
    // before handle_message.
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $next = $stack->next();
        $retriesLeft = $this->maxRetries;

        while (true) {
            try {
                return $next->handle($envelope, $stack);
            } catch (HandlerFailedException $failure) {
                if (!self::causedByConcurrentUpdate($failure) || 0 === $retriesLeft) {
                    throw $failure;
                }

                --$retriesLeft;
            }
        }
    }

    private static function causedByConcurrentUpdate(HandlerFailedException $failure): bool
    {
        return [] !== $failure->getWrappedExceptions(ConcurrentUpdateException::class);
    }
}
