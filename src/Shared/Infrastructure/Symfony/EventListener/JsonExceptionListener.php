<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;

/**
 * Priority -64: after Symfony has logged the exception and given the mapped ones their status
 * (priority 0, `framework.exceptions`) and before its HTML error page (priority -128). The
 * exceptions that no mapping covers answer 500, as Symfony logs them: critical.
 *
 * A request body that cannot be bound to its request class would answer with the serializer's own
 * message, which names the internal class, so it is rewritten from the fields that failed.
 */
#[AsEventListener(event: 'kernel.exception', priority: -64)]
final readonly class JsonExceptionListener
{
    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $isHttp = $exception instanceof HttpExceptionInterface;
        $status = $isHttp ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;

        $error = [
            'code' => $status,
            'message' => $status >= 500 && !$this->debug
                ? 'Internal server error'
                : $this->messageOf($exception),
        ];

        $headers = $isHttp ? $exception->getHeaders() : [];
        $event->setResponse(new JsonResponse(['error' => $error], $status, $headers));
    }

    private function messageOf(\Throwable $exception): string
    {
        $previous = $exception->getPrevious();

        if (!$previous instanceof PartialDenormalizationException) {
            return $exception->getMessage();
        }

        return implode(' ', array_map($this->describe(...), $previous->getNotNormalizableValueErrors()));
    }

    private function describe(NotNormalizableValueException $error): string
    {
        $field = $error->getPath() ?? 'body';

        if ('null' === $error->getCurrentType()) {
            return \sprintf('The "%s" field is required.', $field);
        }

        $expectedTypes = $error->getExpectedTypes() ?? [];

        return \sprintf(
            'The "%s" field must be %s, got %s.',
            $field,
            [] === $expectedTypes ? 'of another type' : 'a '.implode(' or ', $expectedTypes),
            $error->getCurrentType() ?? 'another type',
        );
    }
}
