<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

interface PasswordHasher
{
    public function hash(PlainPassword $password): HashedPassword;

    public function verify(HashedPassword $hash, string $password): bool;

    /**
     * Does the work of verify() against a hash no one owns, so refusing a username that does not
     * exist takes as long as refusing a wrong password.
     */
    public function verifyAgainstNoTechnician(string $password): void;
}
