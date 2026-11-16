<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Infrastructure\Security;

use App\Maintenance\Domain\Technician\HashedPassword;
use App\Maintenance\Domain\Technician\PlainPassword;
use App\Maintenance\Infrastructure\Security\SymfonyPasswordHasher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class SymfonyPasswordHasherTest extends TestCase
{
    public function testGivesAHashThatVerifiesThePasswordWithoutContainingIt(): void
    {
        $nativeHasher = new NativePasswordHasher(cost: 4);

        $hash = new SymfonyPasswordHasher($nativeHasher, new ArrayAdapter())->hash(PlainPassword::fromString('correct-horse'));

        self::assertStringNotContainsString('correct-horse', $hash->value());
        self::assertTrue($nativeHasher->verify($hash->value(), 'correct-horse'));
        self::assertFalse($nativeHasher->verify($hash->value(), 'wrong-horse'));
    }

    public function testVerifiesAPasswordAgainstItsHash(): void
    {
        $nativeHasher = new NativePasswordHasher(cost: 4);
        $hasher = new SymfonyPasswordHasher($nativeHasher, new ArrayAdapter());
        $hash = HashedPassword::fromString($nativeHasher->hash('correct-horse'));

        self::assertTrue($hasher->verify($hash, 'correct-horse'));
        self::assertFalse($hasher->verify($hash, 'wrong-horse'));
    }

    public function testAnotherInstanceReusesTheStoredHashWithoutHashingAgain(): void
    {
        $spy = new class(new NativePasswordHasher(cost: 4)) implements PasswordHasherInterface {
            /** @var list<string> */
            public array $hashed = [];
            /** @var list<string> */
            public array $verifiedHashes = [];

            public function __construct(private readonly PasswordHasherInterface $inner)
            {
            }

            public function hash(#[\SensitiveParameter] string $plainPassword): string
            {
                return $this->hashed[] = $this->inner->hash($plainPassword);
            }

            public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword): bool
            {
                $this->verifiedHashes[] = $hashedPassword;

                return $this->inner->verify($hashedPassword, $plainPassword);
            }

            public function needsRehash(string $hashedPassword): bool
            {
                return $this->inner->needsRehash($hashedPassword);
            }
        };
        $cache = new ArrayAdapter();

        new SymfonyPasswordHasher($spy, $cache)->verifyAgainstNoTechnician('first');
        new SymfonyPasswordHasher($spy, $cache)->verifyAgainstNoTechnician('second');

        self::assertCount(1, $spy->hashed);
        self::assertSame([$spy->hashed[0], $spy->hashed[0]], $spy->verifiedHashes);
    }
}
