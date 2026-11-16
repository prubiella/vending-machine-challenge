<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers a missing, invalid or expired token in the API's error format rather than the bundle's.
 */
final readonly class JwtFailureListener
{
    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onNotFound(JWTNotFoundEvent $event): void
    {
        $event->setResponse(self::unauthorized('Log in as a technician with POST /auth/login and send the token as "Authorization: Bearer <token>".'));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse(self::unauthorized('The token is invalid: log in again.'));
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse(self::unauthorized('The token has expired: log in again.'));
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
