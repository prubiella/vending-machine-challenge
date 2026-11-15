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
    private const string QUIT = 'QUIT';
    private const string SERVICE = 'SERVICE';

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

        $this->runSession($io);

        return Command::SUCCESS;
    }

    private function runSequence(string $sequence, SymfonyStyle $io): void
    {
        foreach (explode(',', $sequence) as $token) {
            $this->perform(trim($token), $io);
        }
        $this->deliverTray($io);
    }

    private function runSession(SymfonyStyle $io): void
    {
        $this->showProducts($io);
        $io->writeln('Insert 0.05, 0.10, 0.25 or 1, choose GET-<SELECTOR>, RETURN-COIN or SERVICE, one per line. QUIT leaves.');

        while (null !== $action = $this->nextAction($io)) {
            if ('' !== $action) {
                $this->act($action, $io);
                $this->deliverTray($io);
            }
        }
    }

    private function act(string $action, SymfonyStyle $io): void
    {
        if (self::SERVICE === $action) {
            $this->service($io);

            return;
        }

        $this->perform($action, $io);
    }

    private function service(SymfonyStyle $io): void
    {
        $status = $this->machineStatus();

        $this->commandBus->dispatch(new ServiceMachineCommand(
            self::askUnitsBySelector($io, $status),
            self::askCoinCountsByCents($io, $status),
        ));
    }

    /**
     * @return array<int|string, int>
     */
    private static function askUnitsBySelector(SymfonyStyle $io, MachineStatusResponse $status): array
    {
        $units = [];
        foreach ($status->unitsBySelector as $selector => $current) {
            $units[$selector] = self::askCount($io, \sprintf('Units of %s', $selector), $current);
        }

        return $units;
    }

    /**
     * @return array<int, int>
     */
    private static function askCoinCountsByCents(SymfonyStyle $io, MachineStatusResponse $status): array
    {
        $counts = [];
        foreach ($status->coinStockByCents as $cents => $current) {
            $counts[$cents] = self::askCount($io, \sprintf('Coins of %s', MoneyNotation::coin($cents)), $current);
        }

        return $counts;
    }

    private static function askCount(SymfonyStyle $io, string $question, int $current): int
    {
        $count = $io->ask($question, (string) $current, self::validCount(...));
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
                MoneyNotation::amount($product->priceCents),
                $product->available ? '' : 'sold out',
            ],
            $this->machine()->products,
        );

        $io->createTable()->setStyle('compact')->setRows($rows)->render();
    }

    private function nextAction(SymfonyStyle $io): ?string
    {
        try {
            $answer = $io->ask(\sprintf('Action (credit %s)', MoneyNotation::amount($this->machine()->creditCents)));
        } catch (MissingInputException) {
            return null;
        }

        $action = \is_string($answer) ? trim($answer) : '';

        return self::QUIT === $action ? null : $action;
    }

    private function perform(string $token, SymfonyStyle $io): void
    {
        try {
            $this->commandBus->dispatch($this->parser->parse($token));
        } catch (RejectedTokenException|DomainException $refusal) {
            $io->getErrorStyle()->writeln($refusal->getMessage());
        }
    }

    private function deliverTray(SymfonyStyle $io): void
    {
        $tray = $this->trayContents();
        $items = [...$tray->selectors, ...array_map(MoneyNotation::coin(...), $tray->coins)];
        if ([] !== $items) {
            $io->writeln(implode(', ', $items));
        }

        $this->commandBus->dispatch(new CollectTrayCommand());
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
