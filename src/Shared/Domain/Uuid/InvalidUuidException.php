<?php

declare(strict_types=1);

namespace App\Shared\Domain\Uuid;

use App\Shared\Domain\DomainException;

final class InvalidUuidException extends DomainException
{
    public static function fromString(string $value): self
    {
        return new self(\sprintf('An identifier is an RFC 4122 UUID such as "019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6c", got "%s".', $value));
    }
}
