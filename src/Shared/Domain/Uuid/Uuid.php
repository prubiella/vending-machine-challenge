<?php

declare(strict_types=1);

namespace App\Shared\Domain\Uuid;

/**
 * The base of every identifier: an RFC 4122 UUID of any version, kept lowercased. Each identifier
 * is a final class of its own (MachineId), so one kind of id never stands in for another.
 */
abstract readonly class Uuid
{
    private const string RFC_4122 = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di';

    // Final, so fromString() can build the subclass it is called on.
    final private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): static
    {
        if (1 !== preg_match(self::RFC_4122, $value)) {
            throw InvalidUuidException::fromString($value);
        }

        return new static(strtolower($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return static::class === $other::class && $this->value === $other->value;
    }
}
