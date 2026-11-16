<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\DomainException;
use App\VendingMachine\Application\Query\GetMachine\GetMachineQuery;
use App\VendingMachine\Application\Query\GetMachine\MachineResponse;
use App\VendingMachine\Application\Query\GetMachine\ProductResponse;
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
        private ServiceForm $serviceForm,
        private TrayPrinter $trayPrinter,
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
        $this->trayPrinter->printAndCollect($io);
    }

    private function runSession(InputInterface $input, SymfonyStyle $io): void
    {
        $this->showProducts($io);
        $io->writeln(TokenParser::sessionUsage());

        while (null !== $action = $this->nextAction($input, $io)) {
            if (TokenParser::QUIT === $action) {
                return;
            }

            $this->dispatchSessionAction($action, $input, $io);
            $this->trayPrinter->printAndCollect($io);
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
        $service = $this->serviceForm->fill($input, $io);
        if (null !== $service) {
            $this->commandBus->dispatch($service);
        }
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

    // Null once the input has ended, by Ctrl+D or in the middle of SERVICE, which ends the session.
    private function nextAction(InputInterface $input, SymfonyStyle $io): ?string
    {
        do {
            $action = $this->askAction($input, $io);
        } while ('' === $action);

        return $action;
    }

    private function askAction(InputInterface $input, SymfonyStyle $io): ?string
    {
        if (!$input->isInteractive()) {
            return null;
        }

        try {
            $answer = $io->ask(\sprintf('Action (credit %s)', MoneyNotation::twoDecimals($this->machine()->creditCents)));
        } catch (MissingInputException) {
            return null;
        }

        return \is_string($answer) ? trim($answer) : '';
    }

    private function dispatchToken(string $token, SymfonyStyle $io): void
    {
        try {
            $this->commandBus->dispatch($this->parser->parse($token));
        } catch (RejectedTokenException|DomainException $refusal) {
            $io->getErrorStyle()->writeln($refusal->getMessage());
        }
    }

    private function machine(): MachineResponse
    {
        $machine = $this->queryBus->query(new GetMachineQuery());
        \assert($machine instanceof MachineResponse);

        return $machine;
    }
}
