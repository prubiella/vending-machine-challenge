<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\QueryBus;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ServiceForm
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    /**
     * @param array<string, int> $givenUnitsBySelector
     * @param array<int, int>    $givenCoinStockByCents
     */
    public function fill(InputInterface $input, SymfonyStyle $io, array $givenUnitsBySelector = [], array $givenCoinStockByCents = []): ?ServiceMachineCommand
    {
        $startedInteractive = $input->isInteractive();
        $status = $this->machineStatus();

        $service = new ServiceMachineCommand(
            self::unitsBySelector($io, $status, $givenUnitsBySelector),
            self::coinStockByCents($io, $status, $givenCoinStockByCents),
        );

        // When the input runs out, the question helper turns it non-interactive and answers every
        // question left with its default, so those answers are not the technician's.
        if ($startedInteractive && !$input->isInteractive()) {
            $io->getErrorStyle()->writeln('The input ended before the service was complete: the machine was not serviced.');

            return null;
        }

        return $service;
    }

    /**
     * @param array<string, int> $given
     *
     * @return array<int|string, int>
     */
    private static function unitsBySelector(SymfonyStyle $io, MachineStatusResponse $status, array $given): array
    {
        $unitsBySelector = [];
        foreach ($status->unitsBySelector as $selector => $currentUnits) {
            $unitsBySelector[$selector] = $given[$selector] ?? self::askCount($io, \sprintf('Units of %s', $selector), $currentUnits);
        }

        // A given selector the machine lacks is kept, so the service refuses it instead of ignoring it.
        return $unitsBySelector + $given;
    }

    /**
     * @param array<int, int> $given
     *
     * @return array<int, int>
     */
    private static function coinStockByCents(SymfonyStyle $io, MachineStatusResponse $status, array $given): array
    {
        $coinStockByCents = [];
        foreach ($status->coinStockByCents as $cents => $currentCount) {
            $coinStockByCents[$cents] = $given[$cents] ?? self::askCount($io, \sprintf('Coins of %s', MoneyNotation::coinToken($cents)), $currentCount);
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
