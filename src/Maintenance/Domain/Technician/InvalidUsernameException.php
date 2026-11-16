<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

use App\Shared\Domain\DomainException;

final class InvalidUsernameException extends DomainException
{
    public static function fromString(string $value): self
    {
        return new self(\sprintf(
            'A username has 3 to 32 lowercase letters, digits, dots, hyphens or underscores, got "%s".',
            $value,
        ));
    }
}
