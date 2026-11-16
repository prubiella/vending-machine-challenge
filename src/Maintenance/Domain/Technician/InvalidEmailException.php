<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

use App\Shared\Domain\DomainException;

final class InvalidEmailException extends DomainException
{
    // The message leaves the value out, since the command bus logs a refusal's message in clear.
    public static function notAnAddress(): self
    {
        return new self('An email is an address such as ana@example.com.');
    }
}
