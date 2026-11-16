<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance\Infrastructure\Security;

use App\Maintenance\Domain\Technician\InvalidCredentialsException;
use App\Shared\Application\Security\TechnicianAuthenticator;
use App\Tests\Integration\Maintenance\HasATechnician;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QueryBusTechnicianAuthenticatorTest extends KernelTestCase
{
    use HasATechnician;

    public function testGivesTheIdOfTheTechnicianWithThatUsernameAndPassword(): void
    {
        $technicianId = self::authenticator()->authenticate(self::TECHNICIAN_USERNAME, self::TECHNICIAN_PASSWORD);

        self::assertSame(self::TECHNICIAN_ID, $technicianId);
    }

    public function testRefusesAWrongPassword(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        self::authenticator()->authenticate(self::TECHNICIAN_USERNAME, 'wrong-horse');
    }

    private static function authenticator(): TechnicianAuthenticator
    {
        return self::getContainer()->get(TechnicianAuthenticator::class);
    }
}
