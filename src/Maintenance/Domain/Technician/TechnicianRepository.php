<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

interface TechnicianRepository
{
    /**
     * @throws UsernameTakenException when another technician has its username
     */
    public function add(Technician $technician): void;

    public function byUsername(Username $username): ?Technician;
}
