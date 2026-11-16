<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Notification;

use Psr\Log\LoggerInterface;

/**
 * The first notifier: a warning in the log. A mail or chat notifier would implement the same port.
 */
final readonly class LoggingLowStockNotifier implements LowStockNotifier
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function stockRunningLow(string $selector, int $unitsLeft): void
    {
        $this->logger->warning(
            \sprintf('%s is running low: %d units left.', $selector, $unitsLeft),
            ['selector' => $selector, 'units_left' => $unitsLeft],
        );
    }
}
