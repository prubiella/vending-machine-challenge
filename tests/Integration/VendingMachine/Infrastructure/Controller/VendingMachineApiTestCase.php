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
        // StartsWithNoStoredMachine boots a kernel to empty the table, and createClient() boots its own.
        self::ensureKernelShutdown();
        $this->client = self::createClient();
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
}
