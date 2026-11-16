<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\HealthCheck;

final readonly class HealthCheckResponse
{
    public const string OK = 'ok';
    public const string KO = 'ko';

    /**
     * @param self::OK                         $status the application itself, which answers whatever its dependencies do
     * @param array<string, self::OK|self::KO> $checks each dependency by its name
     */
    public function __construct(
        public string $status,
        public array $checks,
    ) {
    }
}
