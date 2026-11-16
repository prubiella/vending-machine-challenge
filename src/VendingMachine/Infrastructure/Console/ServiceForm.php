<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\QueryBus;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ServiceForm
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function fill(SymfonyStyle $io): ServiceMachineCommand
    {
        $status = $this->machineStatus();

        return new ServiceMachineCommand(self::askUnitsBySelector($io, $status), self::askCoinStockByCents($io, $status));
    }

    /**
     * @return array<int|string, int>
     */
    private static function askUnitsBySelector(SymfonyStyle $io, MachineStatusResponse $status): array
    {
        $unitsBySelector = [];
        foreach ($status->unitsBySelector as $selector => $currentUnits) {
            $unitsBySelector[$selector] = self::askCount($io, \sprintf('Units of %s', $selector), $currentUnits);
        }

        return $unitsBySelector;
    }

    /**
     * @return array<int, int>
     */
    private static function askCoinStockByCents(SymfonyStyle $io, MachineStatusResponse $status): array
    {
        $coinStockByCents = [];
        foreach ($status->coinStockByCents as $cents => $currentCount) {
            $coinStockByCents[$cents] = self::askCount($io, \sprintf('Coins of %s', MoneyNotation::coinToken($cents)), $currentCount);
        }

        return $coinStockByCents;
    }

    private static function askCount(SymfonyStyle $io, string $question, int $currentCount): int
    {
        $count = $io->ask($question, (string) $currentCount, self::validCount(...));
        \assert(\is_int($count));

        return $count;
    }

    private static function validCount(mixed $answer): int
    {
        if (!\is_string($answer) || !ctype_digit($answer)) {
            throw new \InvalidArgumentException('Enter a whole number, zero or more.');
        }

        return (int) $answer;
    }

    private function machineStatus(): MachineStatusResponse
    {
        $status = $this->queryBus->query(new GetMachineStatusQuery());
        \assert($status instanceof MachineStatusResponse);

        return $status;
    }
}
