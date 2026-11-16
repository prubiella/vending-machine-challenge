<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Maintenance\Application\Command\CreateTechnician\CreateTechnicianCommand;
use App\Shared\Application\Bus\CommandBus;
use App\VendingMachine\Infrastructure\Notation\MoneyNotation;
use App\VendingMachine\Infrastructure\Persistence\StoredMachineResetter;
use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final class TechnicianContext implements Context
{
    /** @var array<string, string> the server parameters of the logged-in technician's requests */
    private array $authHeaders = [];

    public function __construct(
        #[Autowire(service: 'test.client')]
        private readonly KernelBrowser $client,
        private readonly Connection $connection,
        private readonly StoredMachineResetter $storedMachineResetter,
        private readonly CommandBus $commandBus,
    ) {
    }

    #[BeforeScenario]
    public function startWithNoTechnicianAndTheDefaultMachine(): void
    {
        $this->connection->executeStatement('DELETE FROM technicians');
        $this->storedMachineResetter->reset();
        $this->authHeaders = [];
    }

    #[Given('a technician :username with the password :password')]
    public function aTechnicianWithThePassword(string $username, string $password): void
    {
        $this->commandBus->dispatch(new CreateTechnicianCommand(Uuid::v7()->toRfc4122(), $username, $username.'@example.com', $password));
    }

    #[Given('I am logged in as :username with the password :password')]
    public function iAmLoggedIn(string $username, string $password): void
    {
        $this->iLogIn($username, $password);
        $this->iAmLetIn();
        $this->keepTheTokenOfTheLogin();
    }

    #[When('I log in as :username with the password :password')]
    public function iLogIn(string $username, string $password): void
    {
        $this->client->jsonRequest('POST', '/auth/login', ['username' => $username, 'password' => $password]);
    }

    #[When('I ask for the stock and the change')]
    public function iAskForTheStockAndTheChange(): void
    {
        $this->client->request('GET', '/machine/status', server: $this->authHeaders);
    }

    #[Given('a customer paid for :selector with a coin of :coin')]
    public function aCustomerBoughtWithACoin(string $selector, string $coin): void
    {
        $this->client->jsonRequest('POST', '/machine/coins', ['coin' => $coin]);
        $this->client->request('POST', '/machine/selections/'.$selector);
        $this->client->request('POST', '/machine/tray/collect');
        Assert::assertSame(204, $this->client->getResponse()->getStatusCode());
    }

    #[When('I service the machine with :units units of :selector and only :count coins of :coin')]
    public function iServiceTheMachine(int $units, string $selector, int $count, string $coin): void
    {
        $this->client->jsonRequest('PUT', '/machine/service', ['units' => [$selector => $units], 'coins' => [$coin => $count]], $this->authHeaders);
    }

    #[When('I service the machine adding :selector, :name at :price, with :units units')]
    public function iServiceTheMachineAdding(string $selector, string $name, string $price, int $units): void
    {
        $this->serviceTheCatalog([self::product($selector, $name, $price, $units)], []);
    }

    #[When('I service the machine retiring :selector')]
    public function iServiceTheMachineRetiring(string $selector): void
    {
        $this->serviceTheCatalog([], [$selector]);
    }

    #[When('I service the machine replacing :retired by :selector, :name at :price, with :units units')]
    public function iServiceTheMachineReplacing(string $retired, string $selector, string $name, string $price, int $units): void
    {
        $this->serviceTheCatalog([self::product($selector, $name, $price, $units)], [$retired]);
    }

    #[When('a customer pays :amount for :selector')]
    public function aCustomerPaysFor(string $amount, string $selector): void
    {
        foreach (self::coinsWorth(self::cents($amount)) as $coin) {
            $this->client->jsonRequest('POST', '/machine/coins', ['coin' => $coin]);
        }
        $this->client->request('POST', '/machine/selections/'.$selector);
    }

    #[Then('the customer gets :selector')]
    public function theCustomerGets(string $selector): void
    {
        Assert::assertSame(204, $this->client->getResponse()->getStatusCode());
        $this->client->request('GET', '/machine/tray');

        Assert::assertSame([$selector], $this->responseBody()['selectors'] ?? null);
    }

    #[Then('the machine sells no :selector')]
    public function theMachineSellsNo(string $selector): void
    {
        $unitsBySelector = $this->responseBody()['unitsBySelector'] ?? null;
        Assert::assertIsArray($unitsBySelector);

        Assert::assertArrayNotHasKey($selector, $unitsBySelector);
    }

    #[Then('I am let in')]
    public function iAmLetIn(): void
    {
        Assert::assertSame(200, $this->client->getResponse()->getStatusCode());
        Assert::assertIsString($this->responseBody()['token'] ?? null);
    }

    #[Then('the machine holds :units units of :selector')]
    public function theMachineHoldsUnits(int $units, string $selector): void
    {
        $unitsBySelector = $this->responseBody()['unitsBySelector'] ?? null;
        Assert::assertIsArray($unitsBySelector);

        Assert::assertSame($units, $unitsBySelector[$selector] ?? null);
    }

    #[Then('the machine holds :count coins of :coin')]
    public function theMachineHoldsCoins(int $count, string $coin): void
    {
        $coinStockByCents = $this->responseBody()['coinStockByCents'] ?? null;
        Assert::assertIsArray($coinStockByCents);

        Assert::assertSame($count, $coinStockByCents[MoneyNotation::requireCoinCents($coin)] ?? null);
    }

    #[Then('the cash box holds :count coins of :coin')]
    public function theCashBoxHoldsCoins(int $count, string $coin): void
    {
        $cashBoxByCents = $this->responseBody()['cashBoxByCents'] ?? null;
        Assert::assertIsArray($cashBoxByCents);

        Assert::assertSame($count, $cashBoxByCents[MoneyNotation::requireCoinCents($coin)] ?? null);
    }

    #[Then('the machine refuses it saying :excerpt')]
    public function theMachineRefusesIt(string $excerpt): void
    {
        $error = $this->responseBody()['error'];
        \assert(\is_array($error));

        Assert::assertIsString($error['message']);
        Assert::assertStringContainsString($excerpt, $error['message']);
    }

    /**
     * A service that only changes the catalog: the units of the other products stay, and the
     * change is set to what the machine already holds.
     *
     * @param list<array{selector: string, name: string, priceCents: int, units: int}> $install
     * @param list<string>                                                             $retire
     */
    private function serviceTheCatalog(array $install, array $retire): void
    {
        $this->iAskForTheStockAndTheChange();
        $coinStockByCents = $this->responseBody()['coinStockByCents'] ?? null;
        Assert::assertIsArray($coinStockByCents);
        $coins = [];
        foreach ($coinStockByCents as $cents => $count) {
            $coins[MoneyNotation::coinToken((int) $cents)] = $count;
        }

        $this->client->jsonRequest('PUT', '/machine/service', ['units' => [], 'coins' => $coins, 'install' => $install, 'retire' => $retire], $this->authHeaders);
    }

    /**
     * @return array{selector: string, name: string, priceCents: int, units: int}
     */
    private static function product(string $selector, string $name, string $price, int $units): array
    {
        return ['selector' => $selector, 'name' => $name, 'priceCents' => self::cents($price), 'units' => $units];
    }

    private static function cents(string $amount): int
    {
        Assert::assertMatchesRegularExpression('/^\d+\.\d{2}$/', $amount);

        return (int) str_replace('.', '', $amount);
    }

    /**
     * @return list<string> the coins that make up the amount, largest first
     */
    private static function coinsWorth(int $cents): array
    {
        $coins = [];
        foreach ([100, 25, 10, 5] as $coinCents) {
            for (; $cents >= $coinCents; $cents -= $coinCents) {
                $coins[] = MoneyNotation::coinToken($coinCents);
            }
        }

        return $coins;
    }

    private function keepTheTokenOfTheLogin(): void
    {
        $token = $this->responseBody()['token'] ?? null;
        \assert(\is_string($token));
        $this->authHeaders = ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
    }

    /**
     * @return array<mixed>
     */
    private function responseBody(): array
    {
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($body));

        return $body;
    }
}
