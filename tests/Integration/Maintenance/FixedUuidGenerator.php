<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance;

use App\Shared\Application\Uuid\UuidGenerator;

// Gives the same id every time, so a test knows the id of what it creates.
final readonly class FixedUuidGenerator implements UuidGenerator
{
    public const string ID = '0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a6f';

    public function generate(): string
    {
        return self::ID;
    }
}
