<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Response;

final class SelectProductControllerTest extends VendingMachineApiTestCase
{
    public function testDropsTheProductAndTheChangeIntoTheTrayAndAnswersWithNoContent(): void
    {
        $this->insertCoins('0.25', '0.25', '0.25');

        $this->client->request('POST', '/machine/selections/WATER');

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/machine/tray');
        self::assertSame(['selectors' => ['WATER'], 'coins' => [10]], $this->responseBody());
    }

    public function testRefusesAProductTheCreditDoesNotPayAndKeepsTheCredit(): void
    {
        $this->insertCoins('0.25');

        $this->client->request('POST', '/machine/selections/WATER');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(
            ['error' => ['code' => 409, 'message' => 'The credit of 25 cents does not cover the price of 65 cents.']],
            $this->responseBody(),
        );
        self::assertSame(25, $this->credit());
    }

    public function testAnswersAnUnknownSelectorWithNotFound(): void
    {
        $this->client->request('POST', '/machine/selections/COFFEE');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(
            ['error' => ['code' => 404, 'message' => 'There is no product with selector COFFEE.']],
            $this->responseBody(),
        );
    }

    public function testRejectsAMalformedSelector(): void
    {
        $this->client->request('POST', '/machine/selections/water');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
