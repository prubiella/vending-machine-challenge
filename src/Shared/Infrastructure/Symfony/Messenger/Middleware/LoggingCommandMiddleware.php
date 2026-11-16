<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Domain\DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class LoggingCommandMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $command = $envelope->getMessage();
        $name = LoggedCommand::name($command);
        $context = [...LoggedCommand::context($command), 'data' => get_object_vars($command)];

        try {
            $handled = $stack->next()->handle($envelope, $stack);
        } catch (DomainException $refusal) {
            $this->logger->notice(\sprintf('Command %s refused: %s', $name, $refusal->getMessage()), $context);

            throw $refusal;
        } catch (\Throwable $failure) {
            $this->logger->error(\sprintf('Command %s failed: %s', $name, $failure->getMessage()), [...$context, 'exception' => $failure]);

            throw $failure;
        }

        $this->logger->info(\sprintf('Command %s handled.', $name), $context);

        return $handled;
    }
}
