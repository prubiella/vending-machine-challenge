<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\DomainException;
use App\VendingMachine\Application\Command\CollectTray\CollectTrayCommand;
use App\VendingMachine\Application\Query\GetTrayContents\GetTrayContentsQuery;
use App\VendingMachine\Application\Query\GetTrayContents\TrayContentsResponse;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'vending:run', description: 'Run a sequence of actions on the vending machine and print what drops into the tray')]
final readonly class VendingRunCommand
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private TokenParser $parser,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Actions separated by commas, such as "1, 0.25, 0.25, GET-SODA"')]
        string $sequence,
    ): int {
        foreach (explode(',', $sequence) as $token) {
            $this->perform(trim($token), $io);
        }
        $this->deliverTray($io);

        return Command::SUCCESS;
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

    private function trayContents(): TrayContentsResponse
    {
        $tray = $this->queryBus->query(new GetTrayContentsQuery());
        \assert($tray instanceof TrayContentsResponse);

        return $tray;
    }
}
