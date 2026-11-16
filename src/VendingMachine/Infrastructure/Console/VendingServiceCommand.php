<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\VendingMachine\Infrastructure\Notation\MoneyNotation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'vending:service',
    description: 'As a technician, set the units of each product and the count of each coin, asking what the options leave out, and install or retire products',
)]
final readonly class VendingServiceCommand
{
    // A colon rather than "=", since make reads a word with "=" as one of its variables.
    private const string PAIR_SEPARATOR = ':';

    public function __construct(
        private MachineServicer $machineServicer,
        private TrayPrinter $trayPrinter,
    ) {
    }

    /**
     * @param list<string> $units
     * @param list<string> $coins
     * @param list<string> $install
     * @param list<string> $retire
     */
    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Option('The units of a product, as SELECTOR:UNITS (repeat it for each product)')]
        array $units = [],
        #[Option('The count of a coin, as COIN:COUNT, such as 0.25:10 (repeat it for each coin)')]
        array $coins = [],
        #[Option('The technician\'s username')]
        ?string $username = null,
        #[Option('The technician\'s password (asked hidden when left out)')]
        #[\SensitiveParameter]
        ?string $password = null,
        #[Option('A product to install, or to redefine, as SELECTOR:PRICE:UNITS:NAME, such as TEA:1.20:12:Green tea (repeat it for each product)')]
        array $install = [],
        #[Option('The selector of a product to retire, with the units left in it (repeat it for each product)')]
        array $retire = [],
    ): int {
        $serviced = $this->machineServicer->service(
            $input,
            $io,
            self::countsByKey('units', $units),
            self::countsByCents($coins),
            $username,
            $password,
            self::products($install),
            $retire,
        );
        if (!$serviced) {
            return Command::FAILURE;
        }

        $this->trayPrinter->printAndCollect($io);
        $io->writeln('The machine is serviced.');

        return Command::SUCCESS;
    }

    /**
     * The name comes last, so it may hold the separator: TEA:1.20:12:Tea: two bags.
     *
     * @param list<string> $install
     *
     * @return list<array{selector: string, name: string, priceCents: int, units: int}>
     */
    private static function products(array $install): array
    {
        $products = [];
        foreach ($install as $product) {
            [$selector, $price, $units, $name] = array_pad(explode(self::PAIR_SEPARATOR, $product, 4), 4, '');
            $priceCents = MoneyNotation::amountCents($price);
            if ('' === $selector || null === $priceCents || !ctype_digit($units) || '' === $name) {
                throw new InvalidOptionException(\sprintf('Give --install as SELECTOR:PRICE:UNITS:NAME, such as TEA:1.20:12:Green tea, got "%s".', $product));
            }
            if (\in_array($selector, array_column($products, 'selector'), true)) {
                throw new InvalidOptionException(\sprintf('Give --install once for each product, got %s twice.', $selector));
            }
            $products[] = ['selector' => $selector, 'name' => $name, 'priceCents' => $priceCents, 'units' => (int) $units];
        }

        return $products;
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
            [$key, $rawCount] = self::splitPair($pair);
            if ('' === $key || !ctype_digit($rawCount)) {
                throw new InvalidOptionException(\sprintf('Give --%s as KEY:COUNT, such as WATER:5 or 0.25:10, got "%s".', $option, $pair));
            }
            if (\array_key_exists($key, $countsByKey)) {
                throw new InvalidOptionException(\sprintf('Give --%s once for each key, got %s twice.', $option, $key));
            }
            $countsByKey[$key] = (int) $rawCount;
        }

        return $countsByKey;
    }

    /**
     * @return array{string, string} the key and the raw count, which is empty without a separator
     */
    private static function splitPair(string $pair): array
    {
        $parts = explode(self::PAIR_SEPARATOR, $pair, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
