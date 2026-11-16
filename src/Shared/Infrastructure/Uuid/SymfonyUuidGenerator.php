<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Uuid;

use App\Shared\Application\Uuid\UuidGenerator;
use Symfony\Component\Uid\Uuid;

final readonly class SymfonyUuidGenerator implements UuidGenerator
{
    public function generate(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
