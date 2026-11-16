<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Response;

final class CollectTrayControllerTest extends VendingMachineApiTestCase
{
    public function testEmptiesTheTrayAndAnswersWithNoContent(): void
    {
        $this->insertCoins('0.25');
        $this->client->request('POST', '/machine/coin-return');

        $this->client->request('POST', '/machine/tray/collect');

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/machine/tray');
        self::assertSame(['selectors' => [], 'coins' => []], $this->responseBody());
    }
}
