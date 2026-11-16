<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine;

use App\Shared\Application\Bus\Command;
use App\Shared\Application\Bus\CommandBus;

// Keeps every command it passes on, so a test reads what an entry point sent.
final class RecordingCommandBus implements CommandBus
{
    /** @var list<Command> */
    public array $dispatched = [];

    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    public function dispatch(Command $command): void
    {
        $this->dispatched[] = $command;
        $this->commandBus->dispatch($command);
    }
}
