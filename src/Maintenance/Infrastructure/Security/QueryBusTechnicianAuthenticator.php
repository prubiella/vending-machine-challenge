<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Security;

use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticatedTechnicianResponse;
use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticateTechnicianQuery;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Application\Security\TechnicianAuthenticator;

final readonly class QueryBusTechnicianAuthenticator implements TechnicianAuthenticator
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function authenticate(string $username, #[\SensitiveParameter] string $password): string
    {
        $authenticated = $this->queryBus->query(new AuthenticateTechnicianQuery($username, $password));
        \assert($authenticated instanceof AuthenticatedTechnicianResponse);

        return $authenticated->technicianId;
    }
}
