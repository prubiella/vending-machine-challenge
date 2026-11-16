<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class StatementExamplesTest extends VendingMachineApiTestCase
{
    /**
     * @return iterable<string, array{list<string>, string, array{selectors: list<string>, coins: list<int>}}>
     */
    public static function examples(): iterable
    {
        yield 'buy a soda with exact change' => [['1', '0.25', '0.25'], '/machine/selections/SODA', ['selectors' => ['SODA'], 'coins' => []]];
        yield 'start adding money and get it back' => [['0.10', '0.10'], '/machine/coin-return', ['selectors' => [], 'coins' => [10, 10]]];
        yield 'buy a water with change' => [['1'], '/machine/selections/WATER', ['selectors' => ['WATER'], 'coins' => [25, 10]]];
    }

    /**
     * @param list<string>                                     $coins
     * @param array{selectors: list<string>, coins: list<int>} $tray
     */
    #[DataProvider('examples')]
    public function testPlaysTheExampleOfTheStatement(array $coins, string $action, array $tray): void
    {
        $this->insertCoins(...$coins);
        $this->client->request('POST', $action);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('GET', '/machine/tray');

        self::assertSame($tray, $this->responseBody());
    }
}
