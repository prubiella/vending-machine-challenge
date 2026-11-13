<?php

declare(strict_types=1);

namespace App\Shared\Domain\Money;

use App\Shared\Domain\DomainException;

final class InsufficientMoneyException extends DomainException
{
    public static function toSubtract(Money $amount, Money $subtrahend): self
    {
        return new self(\sprintf(
            'Cannot subtract %d cents from %d cents.',
            $subtrahend->cents(),
            $amount->cents(),
        ));
    }
}
