<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class InsertCoinControllerTest extends VendingMachineApiTestCase
{
    public function testAddsTheCoinToTheCreditAndAnswersWithNoContent(): void
    {
        $this->client->jsonRequest('POST', '/machine/coins', ['coin' => '0.25']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->client->jsonRequest('POST', '/machine/coins', ['coin' => '1']);

        self::assertSame(125, $this->credit());
    }

    public function testRejectsAForeignCoinAndKeepsTheCredit(): void
    {
        $this->client->jsonRequest('POST', '/machine/coins', ['coin' => '0.50']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(
            ['error' => ['code' => 422, 'message' => 'The machine does not accept a coin of 0.50: it takes 0.05, 0.10, 0.25 and 1.']],
            $this->responseBody(),
        );
        self::assertSame(0, $this->credit());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'no coin' => [[], 'The "coin" field is required.'];
        yield 'a coin written as a number' => [['coin' => 0.25], 'The "coin" field must be a string, got float.'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('malformedBodies')]
    public function testRejectsABodyWithoutACoinInTheStatementNotation(array $body, string $message): void
    {
        $this->client->jsonRequest('POST', '/machine/coins', $body);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(['error' => ['code' => 422, 'message' => $message]], $this->responseBody());
        self::assertSame(0, $this->credit());
    }

    public function testRejectsABodyThatIsNotJson(): void
    {
        $this->client->request('POST', '/machine/coins', server: ['CONTENT_TYPE' => 'application/json'], content: 'garbage');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame(['error' => ['code' => 400, 'message' => 'Request payload contains invalid "json" data.']], $this->responseBody());
    }
}
