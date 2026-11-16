<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Inventory;

use App\Shared\Domain\DomainException;
use App\VendingMachine\Domain\Product\Selector;

final class ContradictoryCatalogChangeException extends DomainException
{
    public static function installedTwice(Selector $selector): self
    {
        return new self(\sprintf('The service installs %s twice.', $selector->value()));
    }

    public static function retiredTwice(Selector $selector): self
    {
        return new self(\sprintf('The service retires %s twice.', $selector->value()));
    }

    public static function installedAndRetired(Selector $selector): self
    {
        return new self(\sprintf('The service both installs and retires %s.', $selector->value()));
    }

    public static function restockedAndInstalled(Selector $selector): self
    {
        return new self(\sprintf('The service sets the units of %s apart from the ones it installs it with.', $selector->value()));
    }

    public static function restockedAndRetired(Selector $selector): self
    {
        return new self(\sprintf('The service sets the units of %s, which it retires.', $selector->value()));
    }
}
