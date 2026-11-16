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
    public function aCustomerPaidWithACoin(string $selector, string $coin): void
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

    #[Then('the machine refuses it with status :status saying :excerpt')]
    public function theMachineRefusesIt(int $status, string $excerpt): void
    {
        $error = $this->responseBody()['error'];
        \assert(\is_array($error));

        Assert::assertSame($status, $this->client->getResponse()->getStatusCode());
        Assert::assertIsString($error['message']);
        Assert::assertStringContainsString($excerpt, $error['message']);
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
