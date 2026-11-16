<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

interface TechnicianRepository
{
    /**
     * @throws UsernameTakenException when another technician has its username
     * @throws EmailTakenException    when another technician has its email
     */
    public function add(Technician $technician): void;

    public function byUsername(Username $username): ?Technician;
}
