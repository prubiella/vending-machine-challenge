<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Security;

use App\Shared\Infrastructure\Symfony\Security\JwtFailureListener;
use Lcobucci\JWT\Signer\Key\FileCouldNotBeRead;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use PHPUnit\Framework\TestCase;

final class JwtFailureListenerTest extends TestCase
{
    public function testThrowsAServerKeyThatCannotBeReadSoTheJsonListenerAnswers500(): void
    {
        $keyFailure = FileCouldNotBeRead::onPath('/var/www/config/jwt/public.pem');
        $event = self::invalidTokenEvent($keyFailure);

        try {
            new JwtFailureListener()->onInvalid($event);
            self::fail('Expected the server failure to be thrown.');
        } catch (\RuntimeException $thrown) {
            self::assertStringStartsWith('The token could not be checked', $thrown->getMessage());
            self::assertSame($keyFailure, $thrown->getPrevious());
        }

        self::assertNull($event->getResponse());
    }

    public function testAnswersAMalformedTokenWith401(): void
    {
        $event = self::invalidTokenEvent(InvalidTokenStructure::missingOrNotEnoughSeparators());

        new JwtFailureListener()->onInvalid($event);

        self::assertSame(401, $event->getResponse()?->getStatusCode());
    }

    public function testAnswersATokenWithAWrongSignatureWith401(): void
    {
        $event = new JWTInvalidEvent(
            new InvalidTokenException('Invalid JWT Token', 0, new JWTDecodeFailureException(JWTDecodeFailureException::UNVERIFIED_TOKEN, 'Unable to verify the given JWT')),
            null,
        );

        new JwtFailureListener()->onInvalid($event);

        self::assertSame(401, $event->getResponse()?->getStatusCode());
    }

    private static function invalidTokenEvent(\Throwable $cause): JWTInvalidEvent
    {
        $decodeFailure = new JWTDecodeFailureException(JWTDecodeFailureException::INVALID_TOKEN, 'Invalid JWT Token', $cause);

        return new JWTInvalidEvent(new InvalidTokenException('Invalid JWT Token', 0, $decodeFailure), null);
    }
}
