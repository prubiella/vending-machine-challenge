<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Controller;

final readonly class LoginRequest
{
    public function __construct(
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}
