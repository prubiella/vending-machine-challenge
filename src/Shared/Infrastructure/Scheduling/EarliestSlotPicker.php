<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Scheduling;

final readonly class EarliestSlotPicker implements SlotPicker
{
    public function pick(array $free): \DateTimeImmutable
    {
        if ([] === $free) {
            throw NoFreeSlotException::among(0);
        }

        usort($free, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);

        return $free[0];
    }
}
