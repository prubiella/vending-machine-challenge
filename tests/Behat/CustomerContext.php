<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use App\VendingMachine\Infrastructure\Notation\MoneyNotation;
use App\VendingMachine\Infrastructure\Persistence\StoredMachineResetter;
use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class CustomerContext implements Context
{
    public function __construct(
        #[Autowire(service: 'test.client')]
        private KernelBrowser $client,
        private StoredMachineResetter $storedMachineResetter,
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {
    }

    #[BeforeScenario]
    public function startFromTheDefaultMachine(): void
    {
        $this->storedMachineResetter->reset();
    }

    #[Given(':selector is sold out')]
    public function selectorIsSoldOut(string $selector): void
    {
        $status = $this->machineStatus();
        $this->serviceMachineWith([...$status->unitsBySelector, $selector => 0], $status->coinStockByCents);
    }

    #[Given('the machine has :count coins of :coin')]
    public function theMachineHasCoins(int $count, string $coin): void
    {
        $status = $this->machineStatus();
        $coinStock = $status->coinStockByCents;
        $coinStock[MoneyNotation::requireCoinCents($coin)] = $count;
        $this->serviceMachineWith($status->unitsBySelector, $coinStock);
    }

    #[When('I insert :coin')]
    public function iInsert(string $coin): void
    {
        $this->client->jsonRequest('POST', '/machine/coins', ['coin' => $coin]);
    }

    #[When('I select :selector')]
    public function iSelect(string $selector): void
    {
        $this->client->request('POST', '/machine/selections/'.$selector);
    }

    #[When('I ask for my coins back')]
    public function iAskForMyCoinsBack(): void
    {
        $this->client->request('POST', '/machine/coin-return');
    }

    #[When('I collect the tray')]
    public function iCollectTheTray(): void
    {
        $this->client->request('POST', '/machine/tray/collect');
    }

    #[Then('/^the tray holds (no product|[A-Z]+) and (no coins|the coins .+)$/')]
    public function theTrayHolds(string $product, string $coins): void
    {
        $this->client->request('GET', '/machine/tray');

        Assert::assertSame(
            ['selectors' => $this->productsFromStep($product), 'coins' => $this->coinCentsFromStep($coins)],
            $this->responseBody(),
        );
    }

    #[Then('the tray is empty')]
    public function theTrayIsEmpty(): void
    {
        $this->theTrayHolds('no product', 'no coins');
    }

    #[Then('my credit is :amount')]
    public function myCreditIs(string $amount): void
    {
        $this->client->request('GET', '/machine');
        $credit = $this->responseBody()['creditCents'];
        \assert(\is_int($credit));

        Assert::assertSame($amount, MoneyNotation::twoDecimals($credit));
    }

    #[Then('the machine refuses it with status :status saying :messageFragment')]
    public function theMachineRefusesIt(int $status, string $messageFragment): void
    {
        Assert::assertSame($status, $this->client->getResponse()->getStatusCode());

        $error = $this->responseBody()['error'];
        \assert(\is_array($error));

        Assert::assertIsString($error['message']);
        Assert::assertStringContainsString($messageFragment, $error['message']);
    }

    /**
     * @param array<int|string, int> $unitsBySelector
     * @param array<int, int>        $coinStockByCents
     */
    private function serviceMachineWith(array $unitsBySelector, array $coinStockByCents): void
    {
        $this->commandBus->dispatch(new ServiceMachineCommand($unitsBySelector, $coinStockByCents));
    }

    /**
     * @return list<string>
     */
    private function productsFromStep(string $product): array
    {
        return 'no product' === $product ? [] : [$product];
    }

    /**
     * @return list<int>
     */
    private function coinCentsFromStep(string $coins): array
    {
        if ('no coins' === $coins) {
            return [];
        }

        $matched = preg_match('/^the coins (.+)$/', $coins, $matches);
        \assert(1 === $matched);

        return array_map(
            static fn (string $coin): int => MoneyNotation::requireCoinCents(trim($coin)),
            explode(',', $matches[1]),
        );
    }

    private function machineStatus(): MachineStatusResponse
    {
        $status = $this->queryBus->query(new GetMachineStatusQuery());
        \assert($status instanceof MachineStatusResponse);

        return $status;
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
