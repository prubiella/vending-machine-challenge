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
        #[Option('The technician\'s email, stored lowercased')]
        ?string $email = null,
        #[Option('The technician\'s password, of 8 characters or more (asked hidden when left out)')]
        ?string $password = null,
    ): int {
        try {
            $username = self::givenOrAsked($io, $username, 'Username', 'username');
            $email = self::givenOrAsked($io, $email, 'Email', 'email');
            $password = self::givenOrAsked($io, $password, 'Password', 'password', hidden: true);

            $id = Uuid::v7()->toRfc4122();
            $this->commandBus->dispatch(new CreateTechnicianCommand($id, $username, $email, $password));
        } catch (DomainException|MissingValueException $refusal) {
            return self::refuse($io, $refusal->getMessage());
        }

        $io->writeln(\sprintf('Technician %s created with id %s.', $username, $id));

        return Command::SUCCESS;
    }

    /**
     * @throws MissingValueException when the option is left out and the answer is blank, or never asked
     */
    private static function givenOrAsked(SymfonyStyle $io, ?string $option, string $question, string $optionName, bool $hidden = false): string
    {
        $value = $option ?? ($hidden ? $io->askHidden($question) : $io->ask($question));
        if (!\is_string($value) || '' === $value) {
            throw MissingValueException::forOption($optionName);
        }

        return $value;
    }

    private static function refuse(SymfonyStyle $io, string $reason): int
    {
        $io->getErrorStyle()->writeln($reason);

        return Command::FAILURE;
    }
}
