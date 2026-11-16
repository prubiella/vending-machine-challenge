<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

final class InputEndedException extends \RuntimeException
{
    public static function beforeLogin(): self
    {
        return new self('The input ended before the technician logged in.');
    }
}
