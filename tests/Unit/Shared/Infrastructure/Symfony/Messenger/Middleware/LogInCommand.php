<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Bus\Masking;
use App\Shared\Application\Bus\SensitiveCommand;

final readonly class LogInCommand implements SensitiveCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $terminal,
    ) {
    }

    public function sensitiveFields(): array
    {
        return ['email' => Masking::KeepFirstThree, 'password' => Masking::Hide];
    }
}
