<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Scheduling;

final class NoFreeSlotException extends \RuntimeException
{
    public static function among(int $count): self
    {
        return new self(\sprintf('None of the %d slots can be booked.', $count));
    }
}
