<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\Shared\Domain\DomainException;

final class NegativeUnitsException extends DomainException
{
    public static function fromUnits(int $units): self
    {
        return new self(\sprintf('A slot cannot hold a negative number of units, got %d.', $units));
    }
}
