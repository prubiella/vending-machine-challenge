<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

use App\Shared\Domain\DomainException;

final class InvalidCredentialsException extends DomainException
{
    // One message for an unknown username and a wrong password, so a refusal does not tell which
    // usernames exist.
    public static function wrongUsernameOrPassword(): self
    {
        return new self('Wrong username or password.');
    }
}
