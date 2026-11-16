<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use Symfony\Component\Console\Input\InputInterface;

final class InputEnd
{
    /**
     * When the input runs out, Symfony's question helper turns it non-interactive and answers every
     * question left with its default, so those answers are not the technician's.
     *
     * @phpstan-impure the question helper changes the input's interactivity while it asks
     */
    public static function reachedSince(bool $startedInteractive, InputInterface $input): bool
    {
        return $startedInteractive && !$input->isInteractive();
    }
}
