<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain;

use App\Shared\Domain\Uuid\Uuid;

/**
 * The technician who services the machine, as this context knows them: only their id. Maintenance
 * keeps the technicians themselves, and no context uses another (ADR 1).
 */
final readonly class TechnicianId extends Uuid
{
}
