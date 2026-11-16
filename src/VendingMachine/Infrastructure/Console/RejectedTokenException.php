<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

final class RejectedTokenException extends \InvalidArgumentException
{
    public static function unknownAction(string $token, string $usage): self
    {
        return new self(\sprintf('Unknown action "%s": %s.', $token, $usage));
    }
}
