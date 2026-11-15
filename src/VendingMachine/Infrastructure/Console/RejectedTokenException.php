<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

final class RejectedTokenException extends \InvalidArgumentException
{
    public static function foreignCoin(string $token, string $acceptedCoins): self
    {
        return new self(\sprintf('The machine does not accept a coin of %s: it takes %s.', $token, $acceptedCoins));
    }

    public static function unknownAction(string $token, string $usage): self
    {
        return new self(\sprintf('Unknown action "%s": %s.', $token, $usage));
    }
}
