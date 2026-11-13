<?php

declare(strict_types=1);

namespace App\Shared\Domain\Money;

final readonly class Money
{
    private function __construct(private int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw NegativeMoneyException::fromCents($cents);
        }

        return new self($cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        if ($other->cents > $this->cents) {
            throw InsufficientMoneyException::toSubtract($this, $other);
        }

        return new self($this->cents - $other->cents);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->cents >= $other->cents;
    }

    public function isLessThan(self $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function isLessThanOrEqualTo(self $other): bool
    {
        return $this->cents <= $other->cents;
    }
}
