<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Security;

use App\Maintenance\Domain\Technician\HashedPassword;
use App\Maintenance\Domain\Technician\PasswordHasher;
use App\Maintenance\Domain\Technician\PlainPassword;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class SymfonyPasswordHasher implements PasswordHasher
{
    private ?HashedPassword $hashOfNoOne = null;

    public function __construct(
        private readonly PasswordHasherInterface $hasher,
    ) {
    }

    public function hash(PlainPassword $password): HashedPassword
    {
        return HashedPassword::fromString($this->hasher->hash($password->value()));
    }

    public function verify(HashedPassword $hash, #[\SensitiveParameter] string $password): bool
    {
        return $this->hasher->verify($hash->value(), $password);
    }

    public function verifyAgainstNoTechnician(#[\SensitiveParameter] string $password): void
    {
        // Hashed with the configured hasher, so it costs what a real verification costs; once per
        // instance, so only the first refusal pays for the hash as well.
        $this->hashOfNoOne ??= HashedPassword::fromString($this->hasher->hash('no technician has this password'));

        $this->hasher->verify($this->hashOfNoOne->value(), $password);
    }
}
