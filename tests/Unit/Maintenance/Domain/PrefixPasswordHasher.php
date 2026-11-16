<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Domain;

use App\Maintenance\Domain\Technician\HashedPassword;
use App\Maintenance\Domain\Technician\PasswordHasher;
use App\Maintenance\Domain\Technician\PlainPassword;

/**
 * A readable stand-in for a real hasher: the hash is the password behind a prefix.
 */
final readonly class PrefixPasswordHasher implements PasswordHasher
{
    public const string PREFIX = 'hashed:';

    public function hash(PlainPassword $password): HashedPassword
    {
        return HashedPassword::fromString(self::PREFIX.$password->value());
    }

    public function verify(HashedPassword $hash, string $password): bool
    {
        return $hash->value() === self::PREFIX.$password;
    }
}
