<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\Command;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\DomainException;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;

final readonly class ServiceRefusingCommandBus implements CommandBus
{
    public const string REFUSAL = 'The machine refuses the service.';

    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function dispatch(Command $command): void
    {
        if ($command instanceof ServiceMachineCommand) {
            throw new class(self::REFUSAL) extends DomainException {
            };
        }
        $this->commandBus->dispatch($command);
    }
}
