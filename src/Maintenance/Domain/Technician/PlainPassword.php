<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

final readonly class PlainPassword
{
    private const int MIN_LENGTH = 8;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (mb_strlen($value) < self::MIN_LENGTH) {
            throw TooShortPasswordException::below(self::MIN_LENGTH);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
