<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface SensitiveCommand extends Command
{
    /**
     * @return array<string, Masking> how each field that is never logged as it is gets masked, by its name
     */
    public function sensitiveFields(): array;
}
