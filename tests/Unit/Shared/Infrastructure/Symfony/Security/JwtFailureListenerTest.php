<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Security;

use App\Shared\Infrastructure\Symfony\Security\JwtFailureListener;
use Lcobucci\JWT\Signer\Key\FileCouldNotBeRead;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class JwtFailureListenerTest extends TestCase
{
    public function testAnswersAServerKeyThatCannotBeReadWith500AndLogsItAsCritical(): void
    {
        $logs = new TestHandler();
        $event = self::invalidTokenEvent(FileCouldNotBeRead::onPath('/var/www/config/jwt/public.pem'));

        new JwtFailureListener(new Logger('test', [$logs]))->onInvalid($event);

        self::assertSame(500, $event->getResponse()?->getStatusCode());
        self::assertSame('{"error":{"code":500,"message":"Internal server error"}}', $event->getResponse()->getContent());
        self::assertTrue($logs->hasCriticalThatContains('The token could not be checked'));
    }

    public function testAnswersAMalformedTokenWith401(): void
    {
        $logs = new TestHandler();
        $event = self::invalidTokenEvent(InvalidTokenStructure::missingOrNotEnoughSeparators());

        new JwtFailureListener(new Logger('test', [$logs]))->onInvalid($event);

        self::assertSame(401, $event->getResponse()?->getStatusCode());
        self::assertFalse($logs->hasCriticalRecords());
    }

    public function testAnswersATokenWithAWrongSignatureWith401(): void
    {
        $logs = new TestHandler();
        $event = new JWTInvalidEvent(
            new InvalidTokenException('Invalid JWT Token', 0, new JWTDecodeFailureException(JWTDecodeFailureException::UNVERIFIED_TOKEN, 'Unable to verify the given JWT')),
            null,
        );

        new JwtFailureListener(new Logger('test', [$logs]))->onInvalid($event);

        self::assertSame(401, $event->getResponse()?->getStatusCode());
        self::assertFalse($logs->hasCriticalRecords());
    }

    private static function invalidTokenEvent(\Throwable $cause): JWTInvalidEvent
    {
        $decodeFailure = new JWTDecodeFailureException(JWTDecodeFailureException::INVALID_TOKEN, 'Invalid JWT Token', $cause);

        return new JWTInvalidEvent(new InvalidTokenException('Invalid JWT Token', 0, $decodeFailure), null);
    }
}
