<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\HealthCheck;

interface TokenKeysProbe
{
    public function isUsable(): bool;
}
