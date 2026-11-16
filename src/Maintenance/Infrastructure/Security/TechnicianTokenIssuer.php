<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Security;

use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticatedTechnicianResponse;
use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticateTechnicianQuery;
use App\Shared\Application\Bus\QueryBus;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

// The one way a technician's token is made, for the login route and the console alike: the
// credentials are checked with AuthenticateTechnicianQuery, and the token carries the technician's
// id and the technician's role.
final readonly class TechnicianTokenIssuer
{
    private const string TECHNICIAN_ROLE = 'ROLE_TECHNICIAN';

    public function __construct(
        private QueryBus $queryBus,
        private JWTTokenManagerInterface $tokenManager,
    ) {
    }

    public function issue(string $username, #[\SensitiveParameter] string $password): string
    {
        $technician = $this->queryBus->query(new AuthenticateTechnicianQuery($username, $password));
        \assert($technician instanceof AuthenticatedTechnicianResponse);

        return $this->tokenManager->create(new InMemoryUser($technician->technicianId, null, [self::TECHNICIAN_ROLE]));
    }
}
