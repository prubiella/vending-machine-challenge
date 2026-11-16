<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\CashBox;

use App\VendingMachine\Application\Command\ServiceMachine\CashBoxCollectionLog;
use App\VendingMachine\Domain\Coin\CoinCollection;
use Psr\Log\LoggerInterface;

final readonly class LoggerCashBoxCollectionLog implements CashBoxCollectionLog
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function collected(CoinCollection $coins): void
    {
        $this->logger->info(
            \sprintf('The technician collected %d cents from the cash box.', $coins->total()->cents()),
            ['collected_by_cents' => $coins->countsByCents(), 'total_cents' => $coins->total()->cents()],
        );
    }
}
