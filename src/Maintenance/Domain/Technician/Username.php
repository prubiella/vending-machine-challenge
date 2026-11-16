<?php

declare(strict_types=1);

namespace App\Maintenance\Domain\Technician;

final readonly class Username
{
    private const string PATTERN = '/^[a-z0-9._-]{3,32}$/D';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidUsernameException::notAllowed();
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
