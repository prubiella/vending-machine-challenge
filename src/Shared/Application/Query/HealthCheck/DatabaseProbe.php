<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\HealthCheck;

interface DatabaseProbe
{
    public function isReachable(): bool;
}
