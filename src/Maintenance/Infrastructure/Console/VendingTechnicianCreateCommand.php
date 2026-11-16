<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Console;

use App\Maintenance\Application\Command\CreateTechnician\CreateTechnicianCommand;
use App\Maintenance\Domain\Technician\Email;
use App\Maintenance\Domain\Technician\PlainPassword;
use App\Maintenance\Domain\Technician\Username;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\DomainException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'vending:technician:create',
    description: 'Create a technician who can service the machine, asking what the options leave out',
)]
final readonly class VendingTechnicianCreateCommand
{
    private const int ATTEMPTS = 3;

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
            $username = self::givenOrAsked($io, $username, 'username', new Question('Username'), Username::fromString(...));
            $email = self::givenOrAsked($io, $email, 'email', new Question('Email'), Email::fromString(...));
            $password = self::givenOrAsked($io, $password, 'password', self::untrimmedHiddenQuestion('Password'), PlainPassword::fromString(...));

            $id = Uuid::v7()->toRfc4122();
            $this->commandBus->dispatch(new CreateTechnicianCommand($id, $username, $email, $password));
        } catch (DomainException|MissingValueException $refusal) {
            return self::refuse($io, $refusal->getMessage());
        }

        $io->writeln(\sprintf('Technician %s created with id %s.', $username, $id));

        return Command::SUCCESS;
    }

    // A given option goes to the command as it is, so the domain refuses it there at once. An answer
    // is checked with the domain's rule as it is typed, and asked again when refused; after the last
    // attempt the domain's refusal ends the command. An empty option, or no answer as with -n, is
    // missing.
    /**
     * @param \Closure(string): object $domainRule
     */
    private static function givenOrAsked(SymfonyStyle $io, ?string $option, string $optionName, Question $question, \Closure $domainRule): string
    {
        if (null !== $option && '' !== $option) {
            return $option;
        }

        $question
            ->setValidator(static function (mixed $answer) use ($domainRule): string {
                $value = \is_string($answer) ? $answer : '';
                $domainRule($value);

                return $value;
            })
            ->setMaxAttempts(self::ATTEMPTS)
        ;
        $value = null === $option ? $io->askQuestion($question) : null;
        if (!\is_string($value) || '' === $value) {
            throw MissingValueException::forOption($optionName);
        }

        return $value;
    }

    // A password is never trimmed, here as in --password and the login, so the spaces typed are kept;
    // only the line ending that closes the answer goes.
    private static function untrimmedHiddenQuestion(string $question): Question
    {
        return new Question($question)
            ->setHidden(true)
            ->setTrimmable(false)
            ->setNormalizer(static fn (mixed $answer): mixed => \is_string($answer) ? rtrim($answer, "\r\n") : $answer)
        ;
    }

    private static function refuse(SymfonyStyle $io, string $reason): int
    {
        $io->getErrorStyle()->writeln($reason);

        return Command::FAILURE;
    }
}
