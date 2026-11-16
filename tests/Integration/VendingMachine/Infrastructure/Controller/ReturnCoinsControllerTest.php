<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Response;

final class ReturnCoinsControllerTest extends VendingMachineApiTestCase
{
    public function testDropsTheInsertedCoinsIntoTheTrayAndAnswersWithNoContent(): void
    {
        $this->insertCoins('0.25', '1');

        $this->client->request('POST', '/machine/coin-return');

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        self::assertSame(0, $this->credit());
        $this->client->request('GET', '/machine/tray');
        self::assertSame(['selectors' => [], 'coins' => [100, 25]], $this->responseBody());
    }
}
