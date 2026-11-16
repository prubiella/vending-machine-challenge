<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Messenger\Bus;

use App\Shared\Application\Bus\Command;
use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerCommandBus implements CommandBus
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(Command $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
