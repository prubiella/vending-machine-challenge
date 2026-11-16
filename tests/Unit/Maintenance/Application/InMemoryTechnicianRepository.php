<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Application;

use App\Maintenance\Domain\Technician\EmailTakenException;
use App\Maintenance\Domain\Technician\Technician;
use App\Maintenance\Domain\Technician\TechnicianRepository;
use App\Maintenance\Domain\Technician\Username;
use App\Maintenance\Domain\Technician\UsernameTakenException;

final class InMemoryTechnicianRepository implements TechnicianRepository
{
    /** @var array<string, Technician> by username */
    private array $technicians = [];

    public function add(Technician $technician): void
    {
        $username = $technician->username()->value();
        if (\array_key_exists($username, $this->technicians)) {
            throw UsernameTakenException::alreadyTaken();
        }
        foreach ($this->technicians as $stored) {
            if ($stored->email()->value() === $technician->email()->value()) {
                throw EmailTakenException::alreadyTaken();
            }
        }

        $this->technicians[$username] = $technician;
    }

    public function byUsername(Username $username): ?Technician
    {
        return $this->technicians[$username->value()] ?? null;
    }

    public function count(): int
    {
        return \count($this->technicians);
    }
}
