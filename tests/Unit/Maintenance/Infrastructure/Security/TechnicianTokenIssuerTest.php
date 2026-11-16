<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Infrastructure\Security;

use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticatedTechnicianResponse;
use App\Maintenance\Infrastructure\Security\TechnicianTokenIssuer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

final class TechnicianTokenIssuerTest extends TestCase
{
    public function testSignsATokenForTheAuthenticatedTechnicianWithTheTechnicianRole(): void
    {
        $tokenManager = $this->createMock(JWTTokenManagerInterface::class);
        $tokenManager->expects(self::once())
            ->method('create')
            ->with(self::callback(static fn (UserInterface $user): bool => '0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61' === $user->getUserIdentifier()
                && [TechnicianTokenIssuer::TECHNICIAN_ROLE] === $user->getRoles()))
            ->willReturn('a.signed.token')
        ;

        $token = new TechnicianTokenIssuer($tokenManager)->issueFor(new AuthenticatedTechnicianResponse('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61'));

        self::assertSame('a.signed.token', $token);
    }
}
