<?php

declare(strict_types=1);

namespace App\Application\Query\HealthCheck;

use Symfony\Component\HttpKernel\Kernel;

final readonly class HealthCheckQueryHandler
{
    /**
     * @return array{php: string, symfony: string}
     */
    public function __invoke(HealthCheckQuery $query): array
    {
        return [
            'php' => PHP_VERSION,
            'symfony' => Kernel::VERSION,
        ];
    }
}
