<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

interface PasswordHasher
{
    public function hash(PlainPassword $password): HashedPassword;
}
