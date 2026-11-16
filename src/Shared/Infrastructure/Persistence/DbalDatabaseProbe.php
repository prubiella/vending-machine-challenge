<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\Query\HealthCheck\DatabaseProbe;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class DbalDatabaseProbe implements DatabaseProbe
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function isReachable(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
