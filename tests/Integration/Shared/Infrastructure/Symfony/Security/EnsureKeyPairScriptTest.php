<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Symfony\Security;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

/**
 * `make jwt-keys` runs this script: it must look where the bundle writes, never at fixed paths.
 */
final class EnsureKeyPairScriptTest extends TestCase
{
    private const string SCRIPT = __DIR__.'/../../../../../../etc/jwt/ensure-key-pair.php';

    private string $directory;

    #[Before]
    protected function createDirectory(): void
    {
        $this->directory = sys_get_temp_dir().'/ensure-key-pair-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[After]
    protected function removeDirectory(): void
    {
        array_map(unlink(...), glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }

    public function testKeepsAPairKeptAtTheConfiguredPaths(): void
    {
        file_put_contents($this->privateKey(), 'private sentinel');
        file_put_contents($this->publicKey(), 'public sentinel');

        $exitCode = $this->runScript();

        self::assertSame(0, $exitCode);
        self::assertSame('private sentinel', file_get_contents($this->privateKey()));
        self::assertSame('public sentinel', file_get_contents($this->publicKey()));
    }

    public function testCreatesThePairAtTheConfiguredPathsWhenNeitherKeyExists(): void
    {
        $exitCode = $this->runScript();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('PRIVATE KEY', (string) file_get_contents($this->privateKey()));
        self::assertStringContainsString('PUBLIC KEY', (string) file_get_contents($this->publicKey()));
    }

    public function testMakesThePairAgainWhenOneKeyIsMissing(): void
    {
        file_put_contents($this->privateKey(), 'private sentinel');

        $exitCode = $this->runScript();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('PRIVATE KEY', (string) file_get_contents($this->privateKey()));
        self::assertFileExists($this->publicKey());
    }

    private function runScript(): int
    {
        $process = proc_open(
            [\PHP_BINARY, self::SCRIPT],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            [...getenv(), 'JWT_SECRET_KEY' => $this->privateKey(), 'JWT_PUBLIC_KEY' => $this->publicKey(), 'JWT_PASSPHRASE' => ''],
        );
        self::assertIsResource($process);

        return proc_close($process);
    }

    private function privateKey(): string
    {
        return $this->directory.'/private.pem';
    }

    private function publicKey(): string
    {
        return $this->directory.'/public.pem';
    }
}
