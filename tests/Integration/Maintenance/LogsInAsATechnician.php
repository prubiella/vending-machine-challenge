<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * For a WebTestCase: logs in as the technician that HasATechnician stores.
 */
trait LogsInAsATechnician
{
    use HasATechnician;

    /**
     * @return array<string, string> the server parameters that send the technician's token
     */
    private static function asTechnician(KernelBrowser $client): array
    {
        $client->jsonRequest('POST', '/auth/login', ['username' => self::TECHNICIAN_USERNAME, 'password' => self::TECHNICIAN_PASSWORD]);
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertIsString($body['token'] ?? null, 'The login gave no token.');

        return ['HTTP_AUTHORIZATION' => 'Bearer '.$body['token']];
    }
}
