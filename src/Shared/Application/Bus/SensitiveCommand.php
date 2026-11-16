<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface SensitiveCommand extends Command
{
    /**
     * @return list<string> the names of the fields that are never logged as they are
     */
    public function sensitiveFields(): array;
}
