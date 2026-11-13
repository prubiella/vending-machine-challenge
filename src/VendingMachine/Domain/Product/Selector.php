<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Product;

final readonly class Selector
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[A-Z0-9_]+$/D', $value)) {
            throw InvalidSelectorException::fromString($value);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
