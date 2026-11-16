<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

use App\Shared\Domain\DomainException;

final class UsernameTakenException extends DomainException
{
    // The message leaves the username out, since the command bus logs a refusal's message in clear.
    public static function alreadyTaken(): self
    {
        return new self('A technician with that username already exists.');
    }
}
