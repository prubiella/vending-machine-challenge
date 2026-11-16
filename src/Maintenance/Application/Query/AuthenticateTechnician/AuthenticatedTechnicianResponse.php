<?php

declare(strict_types=1);

namespace App\Maintenance\Application\Query\AuthenticateTechnician;

final readonly class AuthenticatedTechnicianResponse
{
    public function __construct(
        public string $technicianId,
    ) {
    }
}
