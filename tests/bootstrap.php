<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

// The tests sign and check tokens, so a checkout without keys (a new worktree, CI) gets its own. A
// pair with one key missing is made again: lexik's --skip-if-exists would keep it, half broken.
function ensureJwtKeyPairExists(): void
{
    $projectDir = dirname(__DIR__);
    $keyPaths = array_map(
        static fn (string $variable, string $default): string => str_replace('%kernel.project_dir%', $projectDir, is_string($_SERVER[$variable] ?? null) ? $_SERVER[$variable] : $default),
        ['JWT_SECRET_KEY', 'JWT_PUBLIC_KEY'],
        [$projectDir.'/config/jwt/private.pem', $projectDir.'/config/jwt/public.pem'],
    );
    if ([] === array_filter($keyPaths, static fn (string $path): bool => !is_file($path))) {
        return;
    }

    $command = sprintf(
        '%s %s lexik:jwt:generate-keypair --overwrite --env=test --no-interaction > /dev/null',
        \PHP_BINARY,
        escapeshellarg($projectDir.'/bin/console'),
    );
    passthru($command, $exitCode);
    if (0 !== $exitCode) {
        exit($exitCode);
    }
}

ensureJwtKeyPairExists();
