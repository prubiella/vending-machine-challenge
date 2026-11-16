<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\EventListener;

use App\Shared\Domain\DomainException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Priority -64: after Symfony has logged the exception (priority 0) and before its HTML error page
 * (priority -128).
 */
#[AsEventListener(event: 'kernel.exception', priority: -64)]
final readonly class JsonExceptionListener
{
    /**
     * @param array<class-string<\Throwable>, int> $statusByException
     */
    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $debug,
        private array $statusByException,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $status = $this->status($exception);

        $error = [
            'code' => $status,
            'message' => $status >= 500 && !$this->debug
                ? 'Internal server error'
                : $exception->getMessage(),
        ];

        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $event->setResponse(new JsonResponse(['error' => $error], $status, $headers));
    }

    private function status(\Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        if (isset($this->statusByException[$exception::class])) {
            return $this->statusByException[$exception::class];
        }

        if ($exception instanceof DomainException) {
            return Response::HTTP_BAD_REQUEST;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
