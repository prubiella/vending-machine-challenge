<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Domain\Technician;

use App\Maintenance\Domain\Technician\TechnicianId;
use App\Shared\Domain\Uuid\InvalidUuidException;
use PHPUnit\Framework\TestCase;

final class TechnicianIdTest extends TestCase
{
    public function testIsAUuidKeptLowercased(): void
    {
        $id = TechnicianId::fromString('0196F1D2-7C3A-7B9E-9A1D-3F5E8C2B4A61');

        self::assertSame('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61', $id->value());
    }

    public function testRefusesAnIdThatIsNotAUuid(): void
    {
        $this->expectException(InvalidUuidException::class);

        TechnicianId::fromString('technician-1');
    }
}
