<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Security;

use App\Shared\Infrastructure\Symfony\Security\LexikTokenKeysProbe;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\LcobucciJWSProvider;
use Lexik\Bundle\JWTAuthenticationBundle\Services\KeyLoader\RawKeyLoader;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class LexikTokenKeysProbeTest extends TestCase
{
    public function testAPairThatSignsAndVerifiesIsUsable(): void
    {
        [$privateKey, $publicKey] = self::keyPair();
        $logs = new TestHandler();

        self::assertTrue(self::probe($privateKey, $publicKey, $logs)->isUsable());
        self::assertFalse($logs->hasWarningRecords());
    }

    public function testAPublicKeyOfAnotherPairIsNotUsableAndLogsWhy(): void
    {
        [$privateKey] = self::keyPair();
        [, $publicKeyOfAnotherPair] = self::keyPair();
        $logs = new TestHandler();

        self::assertFalse(self::probe($privateKey, $publicKeyOfAnotherPair, $logs)->isUsable());
        self::assertTrue($logs->hasWarningThatContains('The JWT key pair is not usable'));
    }

    public function testAMissingKeyIsNotUsable(): void
    {
        [, $publicKey] = self::keyPair();
        $logs = new TestHandler();

        self::assertFalse(self::probe('/nowhere/private.pem', $publicKey, $logs)->isUsable());
        self::assertTrue($logs->hasWarningThatContains('The JWT key pair is not usable'));
    }

    private static function probe(string $privateKey, string $publicKey, TestHandler $logs): LexikTokenKeysProbe
    {
        return new LexikTokenKeysProbe(
            new LcobucciJWSProvider(new RawKeyLoader($privateKey, $publicKey, null), 'RS256', 60, 0),
            new Logger('test', [$logs]),
        );
    }

    /**
     * @return array{string, string} the private key and its public key, in PEM
     */
    private static function keyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $privateKey));
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsString($privateKey);
        self::assertIsString($details['key']);

        return [$privateKey, $details['key']];
    }
}
