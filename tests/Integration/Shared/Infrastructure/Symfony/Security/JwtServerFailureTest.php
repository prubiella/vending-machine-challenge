<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Symfony\Security;

use App\Tests\Integration\Maintenance\LogsInAsATechnician;
use App\Tests\Integration\VendingMachine\Infrastructure\Controller\VendingMachineApiTestCase;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\After;
use Symfony\Component\HttpFoundation\Response;

final class JwtServerFailureTest extends VendingMachineApiTestCase
{
    use LogsInAsATechnician;

    private bool $publicKeyPathChanged = false;
    private ?string $publicKeyPath = null;

    #[After]
    protected function restorePublicKeyPath(): void
    {
        if (!$this->publicKeyPathChanged) {
            return;
        }
        if (null === $this->publicKeyPath) {
            unset($_SERVER['JWT_PUBLIC_KEY'], $_ENV['JWT_PUBLIC_KEY']);
            putenv('JWT_PUBLIC_KEY');

            return;
        }
        $_SERVER['JWT_PUBLIC_KEY'] = $_ENV['JWT_PUBLIC_KEY'] = $this->publicKeyPath;
        putenv('JWT_PUBLIC_KEY='.$this->publicKeyPath);
    }

    public function testAnswers500AndLogsCriticalWhenThePublicKeyCannotBeRead(): void
    {
        $headers = self::asTechnician($this->client);
        $this->publicKeyPath = \is_string($_SERVER['JWT_PUBLIC_KEY'] ?? null) ? $_SERVER['JWT_PUBLIC_KEY'] : null;
        $this->publicKeyPathChanged = true;
        $_SERVER['JWT_PUBLIC_KEY'] = $_ENV['JWT_PUBLIC_KEY'] = '/no/such/public.pem';
        putenv('JWT_PUBLIC_KEY=/no/such/public.pem');
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $logs = new TestHandler();
        self::getContainer()->get('monolog.logger.request')->pushHandler($logs);

        $this->client->request('GET', '/machine/status', server: $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $error = $this->responseBody()['error'] ?? null;
        self::assertIsArray($error);
        self::assertSame(500, $error['code'] ?? null);
        self::assertTrue($logs->hasCriticalRecords());
    }
}
