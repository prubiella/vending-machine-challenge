<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

// The tests sign and check tokens, so a checkout without keys (a new worktree, CI) gets its own.
require dirname(__DIR__).'/etc/jwt/ensure-key-pair.php';
