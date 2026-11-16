<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\HealthCheck;

final readonly class HealthCheckResponse
{
    public const string OK = 'ok';
    public const string ERROR = 'error';

    /**
     * @param self::OK|self::ERROR $status   ok only when every check is
     * @param self::OK|self::ERROR $database
     */
    public function __construct(
        public string $status,
        public string $database,
    ) {
    }
}
