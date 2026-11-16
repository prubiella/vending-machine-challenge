<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Response;

final class GetTrayContentsControllerTest extends VendingMachineApiTestCase
{
    public function testShowsAnEmptyTrayOnANewMachine(): void
    {
        $this->client->request('GET', '/machine/tray');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['selectors' => [], 'coins' => []], $this->responseBody());
    }
}
