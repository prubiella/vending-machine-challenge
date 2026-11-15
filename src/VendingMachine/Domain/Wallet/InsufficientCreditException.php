<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Wallet;

use App\Shared\Domain\DomainException;
use App\Shared\Domain\Money\Money;

final class InsufficientCreditException extends DomainException
{
    public static function forPrice(Money $price, Money $credit): self
    {
        return new self(\sprintf('The credit of %d cents does not cover the price of %d cents.', $credit->cents(), $price->cents()));
    }
}
