<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Response;

final class GetMachineControllerTest extends VendingMachineApiTestCase
{
    public function testShowsTheProductsAndTheCreditOfTheMachine(): void
    {
        $this->client->request('GET', '/machine');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(
            [
                'products' => [
                    ['selector' => 'WATER', 'name' => 'Water', 'priceCents' => 65, 'available' => true],
                    ['selector' => 'JUICE', 'name' => 'Juice', 'priceCents' => 100, 'available' => true],
                    ['selector' => 'SODA', 'name' => 'Soda', 'priceCents' => 150, 'available' => true],
                ],
                'creditCents' => 0,
            ],
            $this->responseBody(),
        );
    }
}
