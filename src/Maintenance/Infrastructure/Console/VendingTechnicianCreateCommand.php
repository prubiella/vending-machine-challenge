<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Console;

use App\Maintenance\Application\Command\CreateTechnician\CreateTechnicianCommand;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\DomainException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'vending:technician:create',
    description: 'Create a technician who can service the machine, asking what the options leave out',
)]
final readonly class VendingTechnicianCreateCommand
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('The technician\'s username: 3 to 32 lowercase letters, digits, dots, hyphens or underscores')]
        ?string $username = null,
        #[Option('The technician\'s password, of 8 characters or more (asked hidden when left out)')]
        ?string $password = null,
    ): int {
        $username ??= self::answer($io->ask('Username'));
        if (null === $username) {
            return self::refuse($io, 'Give the technician a username with --username.');
        }

        $password ??= self::answer($io->askHidden('Password'));
        if (null === $password) {
            return self::refuse($io, 'Give the technician a password with --password.');
        }

        $id = Uuid::v7()->toRfc4122();
        try {
            $this->commandBus->dispatch(new CreateTechnicianCommand($id, $username, $password));
        } catch (DomainException $refusal) {
            return self::refuse($io, $refusal->getMessage());
        }

        $io->writeln(\sprintf('Technician %s created with id %s.', $username, $id));

        return Command::SUCCESS;
    }

    private static function answer(mixed $answer): ?string
    {
        return \is_string($answer) && '' !== $answer ? $answer : null;
    }

    private static function refuse(SymfonyStyle $io, string $reason): int
    {
        $io->getErrorStyle()->writeln($reason);

        return Command::FAILURE;
    }
}
