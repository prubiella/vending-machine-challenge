<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Symfony\Messenger\Bus\MessengerCommandBus;
use App\Tests\Integration\Maintenance\LogsInAsATechnician;
use App\Tests\Integration\VendingMachine\RecordingCommandBus;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ServiceMachineControllerTest extends VendingMachineApiTestCase
{
    use LogsInAsATechnician;

    private const array DEFAULT_STATUS = [
        'unitsBySelector' => ['WATER' => 10, 'JUICE' => 10, 'SODA' => 10],
        'coinStockByCents' => [5 => 10, 10 => 10, 25 => 10, 100 => 0],
        'cashBoxByCents' => [5 => 0, 10 => 0, 25 => 0, 100 => 0],
    ];

    public function testSetsTheGivenUnitsAndTheWholeCoinStock(): void
    {
        $authHeaders = self::asTechnician($this->client);

        $this->client->jsonRequest('PUT', '/machine/service', [
            'units' => ['WATER' => 2, 'SODA' => 0],
            'coins' => ['0.05' => 1, '0.25' => 3, '1' => 4],
        ], $authHeaders);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        $expectedStatus = [
            'unitsBySelector' => ['WATER' => 2, 'JUICE' => 10, 'SODA' => 0],
            'coinStockByCents' => [5 => 1, 10 => 0, 25 => 3, 100 => 4],
            'cashBoxByCents' => [5 => 0, 10 => 0, 25 => 0, 100 => 0],
        ];
        self::assertSame($expectedStatus, $this->machineStatus($authHeaders));
    }

    public function testEmptiesTheCashBoxOfTheCoinsASaleLeftInIt(): void
    {
        $authHeaders = self::asTechnician($this->client);
        $this->insertCoins('1');
        $this->client->request('POST', '/machine/selections/WATER');
        self::assertSame([5 => 0, 10 => 0, 25 => 0, 100 => 1], $this->machineStatus($authHeaders)['cashBoxByCents'] ?? null);

        $this->client->jsonRequest('PUT', '/machine/service', ['units' => [], 'coins' => []], $authHeaders);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame([5 => 0, 10 => 0, 25 => 0, 100 => 0], $this->machineStatus($authHeaders)['cashBoxByCents'] ?? null);
    }

    public function testDropsTheCreditIntoTheTray(): void
    {
        $this->insertCoins('1');

        $this->client->jsonRequest('PUT', '/machine/service', ['units' => [], 'coins' => ['0.25' => 10]], self::asTechnician($this->client));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(0, $this->credit());
        $this->client->request('GET', '/machine/tray');
        self::assertSame(['selectors' => [], 'coins' => [100]], $this->responseBody());
    }

    public function testServicesTheMachineInTheNameOfTheTechnicianOfTheToken(): void
    {
        $recordingBus = self::recordingCommandBus();

        $this->client->jsonRequest('PUT', '/machine/service', ['units' => [], 'coins' => []], self::bearer(self::TECHNICIAN_ID, 'ROLE_TECHNICIAN'));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $dispatchedCommand = $recordingBus->dispatched[0] ?? null;
        self::assertInstanceOf(ServiceMachineCommand::class, $dispatchedCommand);
        self::assertSame(self::TECHNICIAN_ID, $dispatchedCommand->technicianId);
    }

    public function testRefusesARequestWithoutATokenWith401(): void
    {
        $this->client->jsonRequest('PUT', '/machine/service', ['units' => ['WATER' => 0], 'coins' => []]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(self::DEFAULT_STATUS, $this->machineStatus(self::asTechnician($this->client)));
    }

    public function testRefusesATokenWithoutTheTechnicianRoleWith403(): void
    {
        $this->client->jsonRequest('PUT', '/machine/service', ['units' => ['WATER' => 0], 'coins' => []], self::bearer('a-customer', 'ROLE_USER'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(self::DEFAULT_STATUS, $this->machineStatus(self::asTechnician($this->client)));
    }

    public function testRefusesAForeignCoinWith422(): void
    {
        $this->client->jsonRequest('PUT', '/machine/service', ['units' => [], 'coins' => ['0.50' => 3]], self::asTechnician($this->client));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(
            ['error' => ['code' => 422, 'message' => 'The machine does not accept a coin of 0.50: it takes 0.05, 0.10, 0.25 and 1.']],
            $this->responseBody(),
        );
    }

    public function testRefusesAProductTheMachineLacksWith404(): void
    {
        $this->client->jsonRequest('PUT', '/machine/service', ['units' => ['CHIPS' => 3], 'coins' => []], self::asTechnician($this->client));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{array<string, array<string, mixed>>}>
     */
    public static function invalidCounts(): iterable
    {
        yield 'negative units' => [['units' => ['WATER' => -1], 'coins' => []]];
        yield 'a negative count of a coin' => [['units' => [], 'coins' => ['0.25' => -1]]];
        yield 'more units than a slot holds' => [['units' => ['WATER' => 201], 'coins' => []]];
        yield 'more coins than a tube holds' => [['units' => [], 'coins' => ['0.05' => 501]]];
        yield 'a count far past a tube' => [['units' => [], 'coins' => ['0.05' => 100_000_000]]];
        yield 'a count written as text' => [['units' => ['WATER' => 'many'], 'coins' => []]];
        yield 'a count with a fraction' => [['units' => [], 'coins' => ['0.25' => 1.5]]];
        yield 'a count left null' => [['units' => ['WATER' => null], 'coins' => []]];
    }

    /**
     * @param array<string, array<string, mixed>> $body
     */
    #[DataProvider('invalidCounts')]
    public function testRefusesAnInvalidCountWith422AndLeavesTheMachineAsItWas(array $body): void
    {
        $authHeaders = self::asTechnician($this->client);

        $this->client->jsonRequest('PUT', '/machine/service', $body, $authHeaders);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(self::DEFAULT_STATUS, $this->machineStatus($authHeaders));
    }

    public function testRefusesABodyWithoutTheCoinsWith422(): void
    {
        $authHeaders = self::asTechnician($this->client);

        $this->client->jsonRequest('PUT', '/machine/service', ['units' => ['WATER' => 2]], $authHeaders);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(['error' => ['code' => 422, 'message' => 'The "coins" field is required.']], $this->responseBody());
        self::assertSame(self::DEFAULT_STATUS, $this->machineStatus($authHeaders));
    }

    /**
     * @param array<string, string> $authHeaders
     *
     * @return array<mixed>
     */
    private function machineStatus(array $authHeaders): array
    {
        $this->client->request('GET', '/machine/status', server: $authHeaders);

        return $this->responseBody();
    }

    // Replaces the command bus of the client's kernel, so call it before the request.
    private static function recordingCommandBus(): RecordingCommandBus
    {
        $recordingBus = new RecordingCommandBus(new MessengerCommandBus(self::getContainer()->get('command.bus')));
        self::getContainer()->set(CommandBus::class, $recordingBus);

        return $recordingBus;
    }

    /**
     * @return array<string, string>
     */
    private static function bearer(string $identifier, string $role): array
    {
        $token = self::getContainer()->get(JWTTokenManagerInterface::class)->create(new InMemoryUser($identifier, null, [$role]));

        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
    }
}
