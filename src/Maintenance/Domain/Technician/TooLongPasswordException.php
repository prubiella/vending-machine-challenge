<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

use App\Shared\Domain\DomainException;

final class TooLongPasswordException extends DomainException
{
    public static function above(int $maxBytes): self
    {
        return new self(\sprintf('A password has at most %d bytes.', $maxBytes));
    }
}
