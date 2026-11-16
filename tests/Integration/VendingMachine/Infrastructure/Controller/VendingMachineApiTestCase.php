<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class VendingMachineApiTestCase extends WebTestCase
{
    use StartsWithNoStoredMachine;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        // StartsWithNoStoredMachine boots a kernel to forget the stored machine, and createClient() boots its own.
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    protected function insertCoins(string ...$coins): void
    {
        foreach ($coins as $coin) {
            $this->client->jsonRequest('POST', '/machine/coins', ['coin' => $coin]);
        }
    }

    /**
     * @return array<mixed>
     */
    protected function responseBody(): array
    {
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }

    protected function credit(): int
    {
        $this->client->request('GET', '/machine');
        $credit = $this->responseBody()['creditCents'];
        self::assertIsInt($credit);

        return $credit;
    }
}
