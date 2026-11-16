<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use App\Shared\Application\Bus\Command;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Symfony\Messenger\Bus\MessengerCommandBus;
use App\Tests\Integration\Maintenance\LogsInAsATechnician;
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
    ];

    public function testSetsTheGivenUnitsAndTheWholeCoinStock(): void
    {
        $technician = self::asTechnician($this->client);

        $this->client->jsonRequest('PUT', '/machine/service', [
            'units' => ['WATER' => 2, 'SODA' => 0],
            'coins' => ['0.05' => 1, '0.25' => 3, '1' => 4],
        ], $technician);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        self::assertSame(
            ['unitsBySelector' => ['WATER' => 2, 'JUICE' => 10, 'SODA' => 0], 'coinStockByCents' => [5 => 1, 10 => 0, 25 => 3, 100 => 4]],
            $this->machineStatus($technician),
        );
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
        $recordingBus = new class(new MessengerCommandBus(self::getContainer()->get('command.bus'))) implements CommandBus {
            /** @var list<Command> */
            public array $dispatched = [];

            public function __construct(private readonly CommandBus $commandBus)
            {
            }

            public function dispatch(Command $command): void
            {
                $this->dispatched[] = $command;
                $this->commandBus->dispatch($command);
            }
        };
        self::getContainer()->set(CommandBus::class, $recordingBus);

        $this->client->jsonRequest('PUT', '/machine/service', ['units' => [], 'coins' => []], self::bearer(self::TECHNICIAN_ID, 'ROLE_TECHNICIAN'));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $service = $recordingBus->dispatched[0] ?? null;
        self::assertInstanceOf(ServiceMachineCommand::class, $service);
        self::assertSame(self::TECHNICIAN_ID, $service->technicianId);
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
     * @return iterable<string, array{array<string, array<string, int>>}>
     */
    public static function negativeCounts(): iterable
    {
        yield 'negative units' => [['units' => ['WATER' => -1], 'coins' => []]];
        yield 'a negative count of a coin' => [['units' => [], 'coins' => ['0.25' => -1]]];
    }

    /**
     * @param array<string, array<string, int>> $body
     */
    #[DataProvider('negativeCounts')]
    public function testRefusesANegativeCountWith422(array $body): void
    {
        $technician = self::asTechnician($this->client);

        $this->client->jsonRequest('PUT', '/machine/service', $body, $technician);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(self::DEFAULT_STATUS, $this->machineStatus($technician));
    }

    public function testRefusesABodyWithoutTheCoinsWith422(): void
    {
        $technician = self::asTechnician($this->client);

        $this->client->jsonRequest('PUT', '/machine/service', ['units' => ['WATER' => 2]], $technician);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(self::DEFAULT_STATUS, $this->machineStatus($technician));
    }

    /**
     * @param array<string, string> $technician
     *
     * @return array<mixed>
     */
    private function machineStatus(array $technician): array
    {
        $this->client->request('GET', '/machine/status', server: $technician);

        return $this->responseBody();
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
