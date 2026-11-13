<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Messenger\Bus;

use App\Shared\Application\Bus\Command;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class InMemoryCommandBus
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function commandHandler(Command $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
