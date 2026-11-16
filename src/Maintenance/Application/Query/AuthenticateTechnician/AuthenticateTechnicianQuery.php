<?php

declare(strict_types=1);

namespace App\Maintenance\Application\Query\AuthenticateTechnician;

use App\Shared\Application\Bus\Query;

final readonly class AuthenticateTechnicianQuery implements Query
{
    public function __construct(
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}
