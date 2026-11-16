<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class JwtFailureListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onNotFound(JWTNotFoundEvent $event): void
    {
        $event->setResponse(self::unauthorized('Send a technician\'s token as "Authorization: Bearer <token>".'));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onInvalid(JWTInvalidEvent $event): void
    {
        $serverFailure = self::serverFailureBehind($event->getException());
        if (null !== $serverFailure) {
            $this->logger->critical('The token could not be checked: {message}', ['message' => $serverFailure->getMessage(), 'exception' => $serverFailure]);
            $event->setResponse(new JsonResponse(
                ['error' => ['code' => Response::HTTP_INTERNAL_SERVER_ERROR, 'message' => 'Internal server error']],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ));

            return;
        }

        $event->setResponse(self::unauthorized('The token is invalid: log in again.'));
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse(self::unauthorized('The token has expired: log in again.'));
    }

    // A token that cannot be parsed fails in lcobucci's Token or Encoding namespaces, and one with a
    // wrong signature or claims fails with no cause at all: both are the client's. Any other cause,
    // such as a key file that cannot be read, is the server's, and logging in again cannot fix it.
    private static function serverFailureBehind(\Throwable $failure): ?\Throwable
    {
        for ($cause = $failure->getPrevious(); null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof JWTDecodeFailureException) {
                continue;
            }

            return self::isTheClientsToken($cause) ? null : $cause;
        }

        return null;
    }

    private static function isTheClientsToken(\Throwable $cause): bool
    {
        return str_starts_with($cause::class, 'Lcobucci\\JWT\\Token\\')
            || str_starts_with($cause::class, 'Lcobucci\\JWT\\Encoding\\');
    }

    private static function unauthorized(string $message): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => Response::HTTP_UNAUTHORIZED, 'message' => $message]],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer'],
        );
    }
}
