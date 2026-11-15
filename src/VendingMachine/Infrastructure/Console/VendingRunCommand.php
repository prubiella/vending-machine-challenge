<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\DomainException;
use App\VendingMachine\Application\Command\CollectTray\CollectTrayCommand;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Query\GetMachine\GetMachineQuery;
use App\VendingMachine\Application\Query\GetMachine\MachineResponse;
use App\VendingMachine\Application\Query\GetMachine\ProductResponse;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use App\VendingMachine\Application\Query\GetTrayContents\GetTrayContentsQuery;
use App\VendingMachine\Application\Query\GetTrayContents\TrayContentsResponse;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'vending:run',
    description: 'Run a sequence of actions on the vending machine and print what drops into the tray, or an interactive session without one',
)]
final readonly class VendingRunCommand
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private TokenParser $parser,
    ) {
    }

    /**
     * @param list<string> $sequence
     */
    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument('Actions separated by commas, such as "1, 0.25, 0.25, GET-SODA"')]
        array $sequence = [],
    ): int {
        if ([] !== $sequence) {
            // make run splits a quoted sequence into words, so they are joined back before splitting at the commas.
            $this->runSequence(implode(' ', $sequence), $io);

            return Command::SUCCESS;
        }

        if (!$input->isInteractive()) {
            $io->getErrorStyle()->writeln('Give a sequence of actions, such as "1, GET-WATER": the interactive session needs input, which -n and -q turn off.');

            return Command::FAILURE;
        }

        $this->runSession($input, $io);

        return Command::SUCCESS;
    }

    private function runSequence(string $sequence, SymfonyStyle $io): void
    {
        foreach (explode(',', $sequence) as $token) {
            $this->dispatchToken(trim($token), $io);
        }
        $this->printAndCollectTray($io);
    }

    private function runSession(InputInterface $input, SymfonyStyle $io): void
    {
        $this->showProducts($io);
        $io->writeln(TokenParser::sessionUsage());

        while (null !== $action = $this->nextAction($input, $io)) {
            $this->dispatchSessionAction($action, $input, $io);
            $this->printAndCollectTray($io);
        }
    }

    private function dispatchSessionAction(string $action, InputInterface $input, SymfonyStyle $io): void
    {
        if (TokenParser::SERVICE === $action) {
            $this->service($input, $io);

            return;
        }

        $this->dispatchToken($action, $io);
    }

    private function service(InputInterface $input, SymfonyStyle $io): void
    {
        $status = $this->machineStatus();
        $unitsBySelector = self::askUnitsBySelector($io, $status);
        $coinStockByCents = self::askCoinStockByCents($io, $status);

        // When the input runs out, the question helper turns it non-interactive and answers every
        // question left with its default, so those answers are not the technician's.
        if (!$input->isInteractive()) {
            $io->getErrorStyle()->writeln('The input ended before the service was complete: the machine was not serviced.');

            return;
        }

        $this->commandBus->dispatch(new ServiceMachineCommand($unitsBySelector, $coinStockByCents));
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

    private function showProducts(SymfonyStyle $io): void
    {
        $rows = array_map(
            static fn (ProductResponse $product): array => [
                $product->selector,
                $product->name,
                MoneyNotation::twoDecimals($product->priceCents),
                $product->available ? '' : 'sold out',
            ],
            $this->machine()->products,
        );

        $io->createTable()->setStyle('compact')->setRows($rows)->render();
    }

    private function nextAction(InputInterface $input, SymfonyStyle $io): ?string
    {
        while ($input->isInteractive()) {
            try {
                $answer = $io->ask(\sprintf('Action (credit %s)', MoneyNotation::twoDecimals($this->machine()->creditCents)));
            } catch (MissingInputException) {
                return null;
            }

            $action = \is_string($answer) ? trim($answer) : '';
            if ('' !== $action) {
                return TokenParser::QUIT === $action ? null : $action;
            }
        }

        return null;
    }

    private function dispatchToken(string $token, SymfonyStyle $io): void
    {
        try {
            $this->commandBus->dispatch($this->parser->parse($token));
        } catch (RejectedTokenException|DomainException $refusal) {
            $io->getErrorStyle()->writeln($refusal->getMessage());
        }
    }

    private function printAndCollectTray(SymfonyStyle $io): void
    {
        $trayLine = self::trayLine($this->trayContents());
        if ('' !== $trayLine) {
            $io->writeln($trayLine);
        }

        $this->commandBus->dispatch(new CollectTrayCommand());
    }

    private static function trayLine(TrayContentsResponse $tray): string
    {
        $coins = array_map(MoneyNotation::coinToken(...), $tray->coins);

        return implode(', ', [...$tray->selectors, ...$coins]);
    }

    private function machine(): MachineResponse
    {
        $machine = $this->queryBus->query(new GetMachineQuery());
        \assert($machine instanceof MachineResponse);

        return $machine;
    }

    private function machineStatus(): MachineStatusResponse
    {
        $status = $this->queryBus->query(new GetMachineStatusQuery());
        \assert($status instanceof MachineStatusResponse);

        return $status;
    }

    private function trayContents(): TrayContentsResponse
    {
        $tray = $this->queryBus->query(new GetTrayContentsQuery());
        \assert($tray instanceof TrayContentsResponse);

        return $tray;
    }
}
