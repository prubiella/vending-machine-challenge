<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Security;

use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticatedTechnicianResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

// Shared by the login route and the console, so both sign the same claims.
final readonly class TechnicianTokenIssuer
{
    public const string TECHNICIAN_ROLE = 'ROLE_TECHNICIAN';

    public function __construct(
        private JWTTokenManagerInterface $tokenManager,
    ) {
    }

    public function issueFor(AuthenticatedTechnicianResponse $technician): string
    {
        return $this->tokenManager->create(new InMemoryUser($technician->technicianId, null, [self::TECHNICIAN_ROLE]));
    }
}
