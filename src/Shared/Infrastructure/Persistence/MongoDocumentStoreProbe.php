<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\Query\HealthCheck\DocumentStoreProbe;
use MongoDB\Database;
use MongoDB\Driver\Exception\Exception;
use Psr\Log\LoggerInterface;

final readonly class MongoDocumentStoreProbe implements DocumentStoreProbe
{
    public function __construct(
        private Database $database,
        private LoggerInterface $logger,
    ) {
    }

    public function isReachable(): bool
    {
        try {
            $this->database->command(['ping' => 1]);

            return true;
        } catch (Exception $exception) {
            $this->logger->warning('The document store is not reachable: {reason}', [
                'reason' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return false;
        }
    }
}
