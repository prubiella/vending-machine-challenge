<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

use App\Shared\Domain\DomainException;

final class TooShortPasswordException extends DomainException
{
    public static function below(int $minLength): self
    {
        return new self(\sprintf('A password has at least %d characters.', $minLength));
    }
}
