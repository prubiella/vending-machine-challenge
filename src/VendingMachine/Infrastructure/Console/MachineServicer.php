<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\DomainException;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use App\VendingMachine\Infrastructure\Notation\MoneyNotation;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class MachineServicer
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private TechnicianLogin $technicianLogin,
    ) {
    }

    /**
     * @param array<string, int> $givenUnits the units of each selector
     * @param array<int, int>    $givenCoins the count of each coin, by its cents
     */
    public function service(
        InputInterface $input,
        SymfonyStyle $io,
        array $givenUnits = [],
        array $givenCoins = [],
        ?string $username = null,
        #[\SensitiveParameter]
        ?string $password = null,
    ): bool {
        $service = $this->askForCompleteService($input, $io, $givenUnits, $givenCoins, $username, $password);
        if (null === $service) {
            return false;
        }

        $cashBox = $this->machineStatus()->cashBoxByCents;

        try {
            $this->commandBus->dispatch($service);
        } catch (DomainException $refusal) {
            $io->getErrorStyle()->writeln($refusal->getMessage());

            return false;
        }

        self::reportCashBox($io, $cashBox);

        return true;
    }

    /**
     * @param array<int, int> $cashBoxByCents
     */
    private static function reportCashBox(SymfonyStyle $io, array $cashBoxByCents): void
    {
        $collected = [];
        $totalCents = 0;
        foreach (array_filter($cashBoxByCents) as $cents => $count) {
            $collected[] = \sprintf('%d x %s', $count, MoneyNotation::coinToken($cents));
            $totalCents += $cents * $count;
        }

        if ([] !== $collected) {
            $io->writeln(\sprintf('Collected from the cash box: %s (%s).', implode(', ', $collected), MoneyNotation::twoDecimals($totalCents)));
        }
    }

    /**
     * @param array<string, int> $givenUnits
     * @param array<int, int>    $givenCoins
     */
    private function askForCompleteService(
        InputInterface $input,
        SymfonyStyle $io,
        array $givenUnits,
        array $givenCoins,
        ?string $username,
        #[\SensitiveParameter]
        ?string $password,
    ): ?ServiceMachineCommand {
        $startedInteractive = $input->isInteractive();
        $status = $this->machineStatus();
        self::rejectUnknownSelectors($status, $givenUnits);

        try {
            $technicianId = $this->technicianLogin->logIn($input, $io, $username, $password);
        } catch (InputEndedException) {
            return self::refuseInputEnded($io);
        }
        if (null === $technicianId) {
            return null;
        }

        $service = new ServiceMachineCommand(
            $technicianId,
            self::unitsBySelector($io, $status, $givenUnits),
            self::coinStockByCents($io, $status, $givenCoins),
        );

        return InputEnd::reachedSince($startedInteractive, $input) ? self::refuseInputEnded($io) : $service;
    }

    private static function refuseInputEnded(SymfonyStyle $io): null
    {
        $io->getErrorStyle()->writeln('The input ended before the service was complete: the machine was not serviced.');

        return null;
    }

    /**
     * @param array<string, int> $givenUnits
     */
    private static function rejectUnknownSelectors(MachineStatusResponse $status, array $givenUnits): void
    {
        foreach (array_keys($givenUnits) as $selector) {
            if (!\array_key_exists($selector, $status->unitsBySelector)) {
                throw new InvalidOptionException(\sprintf('Give --units a product the machine has, such as WATER:5, got %s.', $selector));
            }
        }
    }

    /**
     * @param array<string, int> $givenUnits
     *
     * @return array<int|string, int>
     */
    private static function unitsBySelector(SymfonyStyle $io, MachineStatusResponse $status, array $givenUnits): array
    {
        $newUnits = [];
        foreach ($status->unitsBySelector as $selector => $currentUnits) {
            $newUnits[$selector] = $givenUnits[$selector] ?? self::askCount($io, \sprintf('Units of %s', $selector), $currentUnits);
        }

        return $newUnits;
    }

    /**
     * @param array<int, int> $givenCoins
     *
     * @return array<int, int>
     */
    private static function coinStockByCents(SymfonyStyle $io, MachineStatusResponse $status, array $givenCoins): array
    {
        $newCoins = [];
        foreach ($status->coinStockByCents as $cents => $currentCount) {
            $newCoins[$cents] = $givenCoins[$cents] ?? self::askCount($io, \sprintf('Coins of %s', MoneyNotation::coinToken($cents)), $currentCount);
        }

        return $newCoins;
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
