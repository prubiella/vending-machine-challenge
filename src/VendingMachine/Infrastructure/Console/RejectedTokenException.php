<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

final class RejectedTokenException extends \InvalidArgumentException
{
    public static function foreignCoin(string $token): self
    {
        return new self(\sprintf('The machine does not accept a coin of %s: it takes 0.05, 0.10, 0.25 and 1.', $token));
    }

    public static function unknownAction(string $token): self
    {
        return new self(\sprintf('Unknown action "%s": insert 0.05, 0.10, 0.25 or 1, GET-<SELECTOR> or RETURN-COIN.', $token));
    }
}
