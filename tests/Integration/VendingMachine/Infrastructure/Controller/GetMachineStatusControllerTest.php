<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use App\Tests\Integration\Maintenance\LogsInAsATechnician;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class GetMachineStatusControllerTest extends VendingMachineApiTestCase
{
    use LogsInAsATechnician;

    public function testShowsATechnicianTheUnitsOfEachProductAndTheCountOfEachCoin(): void
    {
        $this->client->request('GET', '/machine/status', server: self::asTechnician($this->client));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(
            [
                'unitsBySelector' => ['WATER' => 10, 'JUICE' => 10, 'SODA' => 10],
                'coinStockByCents' => [5 => 10, 10 => 10, 25 => 10, 100 => 0],
            ],
            $this->responseBody(),
        );
    }

    public function testRefusesARequestWithoutATokenWith401(): void
    {
        $this->client->request('GET', '/machine/status');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertSame(
            ['error' => ['code' => 401, 'message' => 'Send a technician\'s token as "Authorization: Bearer <token>".']],
            $this->responseBody(),
        );
    }

    public function testRefusesAnInvalidTokenWith401(): void
    {
        $this->client->request('GET', '/machine/status', server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-token']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(['error' => ['code' => 401, 'message' => 'The token is invalid: log in again.']], $this->responseBody());
    }

    public function testRefusesATokenSignedWithAnotherKeyWith401(): void
    {
        $logs = new TestHandler();
        self::getContainer()->get(LoggerInterface::class)->pushHandler($logs);

        $this->client->request('GET', '/machine/status', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::tokenSignedWithAThrowawayKey()]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(['error' => ['code' => 401, 'message' => 'The token is invalid: log in again.']], $this->responseBody());
        self::assertFalse($logs->hasCriticalRecords());
    }

    public function testRefusesAnExpiredTokenWith401(): void
    {
        $anHourAgo = time() - 3600;
        $token = self::getContainer()->get(JWTEncoderInterface::class)->encode([
            'sub' => self::TECHNICIAN_ID,
            'roles' => ['ROLE_TECHNICIAN'],
            'iat' => $anHourAgo - 60,
            'exp' => $anHourAgo,
        ]);

        $this->client->request('GET', '/machine/status', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertSame(['error' => ['code' => 401, 'message' => 'The token has expired: log in again.']], $this->responseBody());
    }

    public function testRefusesATokenWithoutTheTechnicianRoleWith403(): void
    {
        $token = self::getContainer()->get(JWTTokenManagerInterface::class)->create(new InMemoryUser('a-customer', null, ['ROLE_USER']));

        $this->client->request('GET', '/machine/status', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(['error' => ['code' => 403, 'message' => 'Access Denied. The user doesn\'t have ROLE_TECHNICIAN.']], $this->responseBody());
    }

    public function testLeavesTheCustomerRoutesOpen(): void
    {
        $this->client->request('GET', '/machine');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    private static function tokenSignedWithAThrowawayKey(): string
    {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($key);
        $encode = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        $signingInput = $encode('{"typ":"JWT","alg":"RS256"}').'.'.$encode(json_encode(
            ['sub' => self::TECHNICIAN_ID, 'roles' => ['ROLE_TECHNICIAN'], 'iat' => time(), 'exp' => time() + 3600],
            \JSON_THROW_ON_ERROR,
        ));
        $signature = '';
        openssl_sign($signingInput, $signature, $key, \OPENSSL_ALGO_SHA256);

        self::assertIsString($signature);

        return $signingInput.'.'.$encode($signature);
    }
}
