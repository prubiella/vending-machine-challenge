<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Turns every exception into a JSON error. Runs after Symfony has logged it (priority 0) and
 * before its HTML error page (priority -128).
 *
 * Body: {"error": {"code": 404, "message": "..."}}.
 */
#[AsEventListener(event: 'kernel.exception', priority: -64)]
final readonly class JsonExceptionListener
{
    /**
     * Domain exceptions and the status they map to.
     *
     * @var array<class-string<\Throwable>, int>
     */
    private const array STATUS_BY_EXCEPTION = [];

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $debug,
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

        foreach (self::STATUS_BY_EXCEPTION as $class => $status) {
            if ($exception instanceof $class) {
                return $status;
            }
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
