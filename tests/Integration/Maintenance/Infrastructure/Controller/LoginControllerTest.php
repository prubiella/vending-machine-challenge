<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance\Infrastructure\Controller;

use App\Tests\Integration\Maintenance\LogsInAsATechnician;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class LoginControllerTest extends WebTestCase
{
    use LogsInAsATechnician;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        // HasATechnician boots a kernel to store the technician, and createClient() boots its own.
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    public function testGivesATechnicianTokenForTheRightCredentials(): void
    {
        $this->client->jsonRequest('POST', '/auth/login', ['username' => self::TECHNICIAN_USERNAME, 'password' => self::TECHNICIAN_PASSWORD]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $claims = self::getContainer()->get(JWTEncoderInterface::class)->decode(self::tokenFrom($this->client));
        self::assertSame(self::TECHNICIAN_ID, $claims['sub'] ?? null);
        self::assertSame(['ROLE_TECHNICIAN'], $claims['roles'] ?? null);
    }

    public function testRefusesAWrongPasswordWith401(): void
    {
        $this->client->jsonRequest('POST', '/auth/login', ['username' => self::TECHNICIAN_USERNAME, 'password' => 'wrong-horse']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(['error' => ['code' => 401, 'message' => 'Wrong username or password.']], $this->responseBody());
    }

    public function testRefusesAnUnknownUsernameWithTheSame401AsAWrongPassword(): void
    {
        $this->client->jsonRequest('POST', '/auth/login', ['username' => 'nobody', 'password' => self::TECHNICIAN_PASSWORD]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(['error' => ['code' => 401, 'message' => 'Wrong username or password.']], $this->responseBody());
    }

    public function testRefusesABodyWithoutAPasswordWith422(): void
    {
        $this->client->jsonRequest('POST', '/auth/login', ['username' => self::TECHNICIAN_USERNAME]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(['error' => ['code' => 422, 'message' => 'The "password" field is required.']], $this->responseBody());
    }

    /**
     * @return array<mixed>
     */
    private function responseBody(): array
    {
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }
}
