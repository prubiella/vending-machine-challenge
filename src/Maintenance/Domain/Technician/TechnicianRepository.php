<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

interface TechnicianRepository
{
    public function add(Technician $technician): void;

    public function byUsername(Username $username): ?Technician;
}
