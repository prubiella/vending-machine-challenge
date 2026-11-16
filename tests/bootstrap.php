<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

// The tests sign and check tokens, so a checkout without keys (a new worktree, CI) gets its own.
function ensureJwtKeyPairExists(): void
{
    $command = sprintf(
        '%s %s lexik:jwt:generate-keypair --skip-if-exists --env=test --no-interaction > /dev/null',
        \PHP_BINARY,
        escapeshellarg(dirname(__DIR__).'/bin/console'),
    );
    passthru($command, $exitCode);
    if (0 !== $exitCode) {
        exit($exitCode);
    }
}

ensureJwtKeyPairExists();
