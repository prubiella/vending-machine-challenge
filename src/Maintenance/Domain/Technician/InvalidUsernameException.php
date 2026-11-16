<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

use App\Shared\Domain\DomainException;

final class InvalidUsernameException extends DomainException
{
    // The message leaves the value out, since the command bus logs a refusal's message in clear.
    public static function notAllowed(): self
    {
        return new self('A username has 3 to 32 lowercase letters, digits, dots, hyphens or underscores.');
    }
}
