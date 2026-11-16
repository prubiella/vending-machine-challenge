<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

// Makes the key pair that signs the technicians' tokens unless both keys exist, at the paths the
// bundle writes to: the configured JWT_SECRET_KEY and JWT_PUBLIC_KEY, so a pair kept elsewhere is
// never overwritten. A pair with one key missing is made again, since lexik's --skip-if-exists
// would keep it, half broken. Run by `make jwt-keys` and by the test bootstrap.

$projectDir = dirname(__DIR__, 2);
require $projectDir.'/vendor/autoload.php';

new Dotenv()->bootEnv($projectDir.'/.env');

$keyPaths = array_map(
    static fn (string $variable, string $default): string => str_replace(
        '%kernel.project_dir%',
        $projectDir,
        is_string($_SERVER[$variable] ?? null) ? $_SERVER[$variable] : $default,
    ),
    ['JWT_SECRET_KEY', 'JWT_PUBLIC_KEY'],
    [$projectDir.'/config/jwt/private.pem', $projectDir.'/config/jwt/public.pem'],
);
if ([] === array_filter($keyPaths, static fn (string $path): bool => !is_file($path))) {
    return;
}

$environment = is_string($_SERVER['APP_ENV'] ?? null) ? $_SERVER['APP_ENV'] : 'dev';
$command = sprintf(
    '%s %s lexik:jwt:generate-keypair --overwrite --env=%s --no-interaction > /dev/null',
    escapeshellarg(\PHP_BINARY),
    escapeshellarg($projectDir.'/bin/console'),
    escapeshellarg($environment),
);
passthru($command, $exitCode);
if (0 !== $exitCode) {
    exit($exitCode);
}
