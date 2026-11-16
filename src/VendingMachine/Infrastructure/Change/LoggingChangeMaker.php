<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Change;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\ChangeMaker;
use App\VendingMachine\Domain\Change\NoExactChangeException;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use Psr\Log\LoggerInterface;

final readonly class LoggingChangeMaker implements ChangeMaker
{
    public function __construct(
        private ChangeMaker $changeMaker,
        private LoggerInterface $logger,
    ) {
    }

    public function gives(Coin $coin): bool
    {
        return $this->changeMaker->gives($coin);
    }

    public function makeChange(Money $amount, CoinCollection $available): CoinCollection
    {
        try {
            $change = $this->changeMaker->makeChange($amount, $available);
        } catch (NoExactChangeException $noChange) {
            $this->logger->notice(
                \sprintf('No change computed: %s', $noChange->getMessage()),
                ['amount_cents' => $amount->cents(), 'available_by_cents' => $available->countsByCents()],
            );

            throw $noChange;
        }

        $this->logger->info(
            \sprintf('Change of %d cents computed.', $amount->cents()),
            ['amount_cents' => $amount->cents(), 'change_by_cents' => $change->countsByCents()],
        );

        return $change;
    }
}
