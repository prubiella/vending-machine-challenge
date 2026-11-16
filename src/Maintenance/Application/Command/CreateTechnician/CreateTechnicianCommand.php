<?php

declare(strict_types=1);

namespace App\Maintenance\Application\Command\CreateTechnician;

use App\Shared\Application\Bus\Masking;
use App\Shared\Application\Bus\SensitiveCommand;

final readonly class CreateTechnicianCommand implements SensitiveCommand
{
    public function __construct(
        public string $id,
        public string $username,
        public string $email,
        public string $password,
    ) {
    }

    public function sensitiveFields(): array
    {
        return ['username' => Masking::KeepFirstThree, 'email' => Masking::KeepFirstThree, 'password' => Masking::Hide];
    }
}
