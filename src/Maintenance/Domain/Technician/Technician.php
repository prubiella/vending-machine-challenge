<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

final readonly class Technician
{
    private function __construct(
        private TechnicianId $id,
        private Username $username,
        private Email $email,
        private HashedPassword $password,
    ) {
    }

    public static function register(TechnicianId $id, Username $username, Email $email, HashedPassword $password): self
    {
        return new self($id, $username, $email, $password);
    }

    public function authenticate(string $password, PasswordHasher $hasher): void
    {
        if (!$hasher->verify($this->password, $password)) {
            throw InvalidCredentialsException::wrongUsernameOrPassword();
        }
    }

    public function id(): TechnicianId
    {
        return $this->id;
    }

    public function username(): Username
    {
        return $this->username;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }
}
