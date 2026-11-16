<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Product;

use App\Shared\Domain\DomainException;
use App\VendingMachine\Domain\Coin\Coin;

final class UnpayablePriceException extends DomainException
{
    public static function forProduct(Product $product, Coin $smallestCoin): self
    {
        return new self(\sprintf(
            'The price of %s, %d cents, is not a multiple of the smallest coin, %d cents.',
            $product->selector()->value(),
            $product->price()->cents(),
            $smallestCoin->value,
        ));
    }
}
