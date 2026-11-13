<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Product;

final readonly class ProductName
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if ('' === trim($value)) {
            throw EmptyProductNameException::fromString($value);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
