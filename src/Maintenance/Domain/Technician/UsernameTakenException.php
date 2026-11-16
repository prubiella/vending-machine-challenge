<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

use App\Shared\Domain\DomainException;

final class UsernameTakenException extends DomainException
{
    public static function of(Username $username): self
    {
        return new self(\sprintf('A technician named "%s" already exists.', $username->value()));
    }
}
