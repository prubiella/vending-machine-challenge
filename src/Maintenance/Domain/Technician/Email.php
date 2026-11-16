<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

final readonly class Email
{
    private const int MAX_LENGTH = 254;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (\strlen($value) > self::MAX_LENGTH || false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidEmailException::notAnAddress();
        }

        // Lowercased whole, so the unique index treats Ana@Example.com and ana@example.com as one.
        return new self(mb_strtolower($value));
    }

    public function value(): string
    {
        return $this->value;
    }
}
