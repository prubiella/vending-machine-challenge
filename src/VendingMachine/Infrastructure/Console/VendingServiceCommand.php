<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\DomainException;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'vending:service',
    description: 'Set the units of each product and the count of each coin, asking what the options leave out',
)]
final readonly class VendingServiceCommand
{
    // A colon rather than "=", since make reads a word with "=" as one of its variables.
    private const string PAIR_SEPARATOR = ':';

    public function __construct(
        private CommandBus $commandBus,
        private ServiceForm $serviceForm,
        private TrayPrinter $trayPrinter,
    ) {
    }

    /**
     * @param list<string> $units
     * @param list<string> $coins
     */
    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Option('The units of a product, as SELECTOR:UNITS (repeat it for each product)')]
        array $units = [],
        #[Option('The count of a coin, as COIN:COUNT, such as 0.25:10 (repeat it for each coin)')]
        array $coins = [],
    ): int {
        $service = $this->serviceForm->fill($input, $io, self::countsByKey('units', $units), self::countsByCents($coins));

        return null === $service ? Command::FAILURE : $this->service($service, $io);
    }

    private function service(ServiceMachineCommand $service, SymfonyStyle $io): int
    {
        try {
            $this->commandBus->dispatch($service);
        } catch (DomainException $refusal) {
            $io->getErrorStyle()->writeln($refusal->getMessage());

            return Command::FAILURE;
        }

        $this->trayPrinter->printAndCollect($io);
        $io->writeln('The machine is serviced.');

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $coins
     *
     * @return array<int, int>
     */
    private static function countsByCents(array $coins): array
    {
        $countsByCents = [];
        foreach (self::countsByKey('coins', $coins) as $coinToken => $count) {
            $cents = MoneyNotation::coinCents((string) $coinToken)
                ?? throw new InvalidOptionException(\sprintf('Give --coins a coin the machine takes, such as 0.25:10, got %s.', $coinToken));
            $countsByCents[$cents] = $count;
        }

        return $countsByCents;
    }

    /**
     * @param list<string> $pairs
     *
     * @return array<string, int>
     */
    private static function countsByKey(string $option, array $pairs): array
    {
        $countsByKey = [];
        foreach ($pairs as $pair) {
            [$key, $count] = explode(self::PAIR_SEPARATOR, $pair, 2) + [1 => ''];
            if ('' === $key || !ctype_digit($count)) {
                throw new InvalidOptionException(\sprintf('Give --%s as KEY:COUNT, such as WATER:5 or 0.25:10, got "%s".', $option, $pair));
            }
            if (\array_key_exists($key, $countsByKey)) {
                throw new InvalidOptionException(\sprintf('Give --%s once for each key, got %s twice.', $option, $key));
            }
            $countsByKey[$key] = (int) $count;
        }

        return $countsByKey;
    }
}
