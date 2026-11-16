<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Infrastructure\Security;

use App\Maintenance\Domain\Technician\PlainPassword;
use App\Maintenance\Infrastructure\Security\SymfonyPasswordHasher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

final class SymfonyPasswordHasherTest extends TestCase
{
    public function testGivesAHashThatVerifiesThePasswordWithoutContainingIt(): void
    {
        $nativeHasher = new NativePasswordHasher(cost: 4);

        $hash = new SymfonyPasswordHasher($nativeHasher)->hash(PlainPassword::fromString('correct-horse'));

        self::assertStringNotContainsString('correct-horse', $hash->value());
        self::assertTrue($nativeHasher->verify($hash->value(), 'correct-horse'));
        self::assertFalse($nativeHasher->verify($hash->value(), 'wrong-horse'));
    }
}
