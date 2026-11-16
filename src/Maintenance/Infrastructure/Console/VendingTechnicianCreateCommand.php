<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Console;

use App\Maintenance\Application\Command\CreateTechnician\CreateTechnicianCommand;
use App\Maintenance\Domain\Technician\Email;
use App\Maintenance\Domain\Technician\PlainPassword;
use App\Maintenance\Domain\Technician\Username;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Uuid\UuidGenerator;
use App\Shared\Domain\DomainException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'vending:technician:create',
    description: 'Create a technician who can service the machine, asking what the options leave out',
)]
final readonly class VendingTechnicianCreateCommand
{
    private const int ATTEMPTS = 3;

    public function __construct(
        private CommandBus $commandBus,
        private UuidGenerator $uuidGenerator,
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
            $resolvedUsername = self::givenOrAsked(
                $io,
                $username,
                'username',
                new Question('Username'),
                Username::fromString(...),
            );
            $resolvedEmail = self::givenOrAsked($io, $email, 'email', new Question('Email'), Email::fromString(...));
            $resolvedPassword = self::givenOrAsked(
                $io,
                $password,
                'password',
                self::untrimmedHiddenQuestion('Password'),
                PlainPassword::fromString(...),
            );

            $id = $this->uuidGenerator->generate();
            $this->commandBus->dispatch(new CreateTechnicianCommand($id, $resolvedUsername, $resolvedEmail, $resolvedPassword));
        } catch (DomainException|MissingValueException $refusal) {
            return self::refuse($io, $refusal->getMessage());
        }

        $io->writeln(\sprintf('Technician %s created with id %s.', $resolvedUsername, $id));

        return Command::SUCCESS;
    }

    /**
     * @param \Closure(string): object $validateWithDomain
     */
    private static function givenOrAsked(
        SymfonyStyle $io,
        ?string $option,
        string $optionName,
        Question $question,
        \Closure $validateWithDomain,
    ): string {
        if (null === $option) {
            return self::asked($io, $optionName, $question, $validateWithDomain);
        }
        if ('' === $option) {
            throw MissingValueException::forOption($optionName);
        }

        // A given option is checked by the domain when the command is handled, so it is not asked again.
        return $option;
    }

    /**
     * @param \Closure(string): object $validateWithDomain
     */
    private static function asked(SymfonyStyle $io, string $optionName, Question $question, \Closure $validateWithDomain): string
    {
        $answer = $io->askQuestion(self::askedAgainUntilTheDomainTakesIt($question, $validateWithDomain));
        // Without interaction nothing is asked, so the answer is null.
        if (!\is_string($answer) || '' === $answer) {
            throw MissingValueException::forOption($optionName);
        }

        return $answer;
    }

    /**
     * @param \Closure(string): object $validateWithDomain
     */
    private static function askedAgainUntilTheDomainTakesIt(Question $question, \Closure $validateWithDomain): Question
    {
        return $question
            ->setValidator(static function (mixed $answer) use ($validateWithDomain): string {
                $value = \is_string($answer) ? $answer : '';
                $validateWithDomain($value);

                return $value;
            })
            ->setMaxAttempts(self::ATTEMPTS)
        ;
    }

    // A password is never trimmed, here as in --password, so the spaces typed are kept; only the line
    // ending that closes the answer goes.
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
