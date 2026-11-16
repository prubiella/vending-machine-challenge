<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

enum Masking
{
    private const string MASK = '***';
    private const int KEPT_CHARACTERS = 3;

    case Hide;
    case KeepFirstThree;

    public function mask(mixed $value): string
    {
        return match ($this) {
            self::Hide => self::MASK,
            self::KeepFirstThree => \is_string($value) && mb_strlen($value) > self::KEPT_CHARACTERS
                ? mb_substr($value, 0, self::KEPT_CHARACTERS).self::MASK
                : self::MASK,
        };
    }
}
