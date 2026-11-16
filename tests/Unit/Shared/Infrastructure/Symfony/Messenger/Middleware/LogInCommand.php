<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Bus\SensitiveCommand;

final readonly class LogInCommand implements SensitiveCommand
{
    public function __construct(
        public string $username,
        public string $password,
    ) {
    }

    public function sensitiveFields(): array
    {
        return ['password'];
    }
}
