<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Scheduling;

interface SlotPicker
{
    /**
     * @param list<\DateTimeImmutable> $free
     */
    public function pick(array $free): \DateTimeImmutable;
}
