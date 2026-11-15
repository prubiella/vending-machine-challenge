<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Scheduling;

use App\Shared\Infrastructure\Scheduling\EarliestSlotPicker;
use App\Shared\Infrastructure\Scheduling\NoFreeSlotException;
use App\Shared\Infrastructure\Scheduling\Scheduler;
use PHPUnit\Framework\TestCase;

final class SchedulerTest extends TestCase
{
    public function testBooksTheEarliestFreeSlot(): void
    {
        $booked = new Scheduler()->book(
            [new \DateTimeImmutable('2026-03-11 10:00'), new \DateTimeImmutable('2026-03-10 09:00')],
            new EarliestSlotPicker(),
            new \DateTimeZone('UTC'),
        );

        self::assertEquals(new \DateTimeImmutable('2026-03-10 09:00'), $booked);
    }

    public function testSkipsWeekendSlots(): void
    {
        $booked = new Scheduler()->book(
            [new \DateTimeImmutable('2026-03-14 09:00'), new \DateTimeImmutable('2026-03-16 09:00')],
            new EarliestSlotPicker(),
            new \DateTimeZone('UTC'),
        );

        self::assertEquals(new \DateTimeImmutable('2026-03-16 09:00'), $booked);
    }

    public function testRefusesWhenOnlyWeekendSlotsAreFree(): void
    {
        $this->expectException(NoFreeSlotException::class);

        new Scheduler()->book([new \DateTimeImmutable('2026-03-14 09:00')], new EarliestSlotPicker(), new \DateTimeZone('UTC'));
    }
}
