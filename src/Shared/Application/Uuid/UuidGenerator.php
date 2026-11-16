<?php

declare(strict_types=1);

namespace App\Shared\Application\Uuid;

/**
 * Gives the id of a new aggregate. Whoever creates one calls it before dispatching the command
 * (commands return nothing), and the context wraps the string in its own identifier.
 */
interface UuidGenerator
{
    public function generate(): string;
}
