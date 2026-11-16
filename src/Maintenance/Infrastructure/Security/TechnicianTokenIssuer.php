<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Security;

use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticatedTechnicianResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

// Signs the token of a technician that AuthenticateTechnicianQuery let in, for the login route and
// the console alike: it carries the technician's id and the role that security.yaml requires.
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
