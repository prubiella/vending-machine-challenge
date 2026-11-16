<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

/**
 * Something that happened to the machine, recorded by the aggregate and saved with it. Its id and
 * the time it happened are given when it is saved, so the domain needs no generator and no clock.
 */
interface VendingMachineEvent
{
}
