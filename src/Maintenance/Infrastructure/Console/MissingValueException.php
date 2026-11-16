<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Console;

final class MissingValueException extends \RuntimeException
{
    public static function forOption(string $option): self
    {
        return new self(\sprintf('Give the technician\'s %1$s with --%1$s.', $option));
    }
}
