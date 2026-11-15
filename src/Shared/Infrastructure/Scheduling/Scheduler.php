<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Scheduling;

final readonly class Scheduler
{
    /**
     * @param list<\DateTimeImmutable> $free
     */
    public function book(array $free, SlotPicker $picker, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $weekdays = array_values(array_filter(
            $free,
            static fn (\DateTimeImmutable $slot): bool => (int) $slot->format('N') < 6,
        ));

        return $picker->pick($weekdays);
    }
}
