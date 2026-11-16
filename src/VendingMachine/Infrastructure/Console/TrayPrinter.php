<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\VendingMachine\Application\Command\CollectTray\CollectTrayCommand;
use App\VendingMachine\Application\Query\GetTrayContents\GetTrayContentsQuery;
use App\VendingMachine\Application\Query\GetTrayContents\TrayContentsResponse;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class TrayPrinter
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {
    }

    public function printAndCollect(SymfonyStyle $io): void
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

    private function trayContents(): TrayContentsResponse
    {
        $tray = $this->queryBus->query(new GetTrayContentsQuery());
        \assert($tray instanceof TrayContentsResponse);

        return $tray;
    }
}
