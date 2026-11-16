<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Notation;

final class ForeignCoinException extends \InvalidArgumentException
{
    public static function fromToken(string $token): self
    {
        return new self(\sprintf('The machine does not accept a coin of %s: it takes %s.', $token, MoneyNotation::coinTokensEndingWith(' and ')));
    }
}
