<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Domain\Technician;

use App\Maintenance\Domain\Technician\Email;
use App\Maintenance\Domain\Technician\HashedPassword;
use App\Maintenance\Domain\Technician\InvalidCredentialsException;
use App\Maintenance\Domain\Technician\Technician;
use App\Maintenance\Domain\Technician\TechnicianId;
use App\Maintenance\Domain\Technician\Username;
use App\Tests\Unit\Maintenance\Domain\PrefixPasswordHasher;
use PHPUnit\Framework\TestCase;

final class TechnicianTest extends TestCase
{
    public function testAuthenticatesWithItsPassword(): void
    {
        $this->expectNotToPerformAssertions();

        self::technicianWithPassword('correct-horse')->authenticate('correct-horse', new PrefixPasswordHasher());
    }

    public function testRefusesAnotherPassword(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        self::technicianWithPassword('correct-horse')->authenticate('wrong-horse', new PrefixPasswordHasher());
    }

    private static function technicianWithPassword(string $password): Technician
    {
        return Technician::register(
            TechnicianId::fromString('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61'),
            Username::fromString('ana'),
            Email::fromString('ana@example.com'),
            HashedPassword::fromString(PrefixPasswordHasher::PREFIX.$password),
        );
    }
}
