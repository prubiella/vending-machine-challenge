<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Security\TechnicianAuthenticator;
use App\Shared\Domain\DomainException;
use App\Shared\Infrastructure\Console\PasswordQuestion;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class TechnicianLogin
{
    public function __construct(
        private TechnicianAuthenticator $technicianAuthenticator,
    ) {
    }

    // Null when the technician is not let in: the reason is on stderr, except when the input ends,
    // which the caller reports, since it ends the caller's questions too.
    public function technicianId(InputInterface $input, SymfonyStyle $io, ?string $givenUsername, #[\SensitiveParameter] ?string $givenPassword): ?string
    {
        $startedInteractive = $input->isInteractive();
        [$username, $password] = self::askForMissingCredentials($input, $io, $givenUsername, $givenPassword);
        if ($startedInteractive && !$input->isInteractive()) {
            return null;
        }

        if (null === $username || null === $password) {
            return self::refuseMissingCredentials($io);
        }

        return $this->verifiedTechnicianId($io, $username, $password);
    }

    /**
     * @return array{?string, ?string} the username and the password, null when not given
     */
    private static function askForMissingCredentials(InputInterface $input, SymfonyStyle $io, ?string $givenUsername, #[\SensitiveParameter] ?string $givenPassword): array
    {
        $username = $givenUsername;
        $password = $givenPassword;
        try {
            $username ??= self::nullIfBlank($io->ask('Username'));
            $password ??= self::nullIfBlank($io->askQuestion(PasswordQuestion::hidden('Password')));
        } catch (MissingInputException) {
            $input->setInteractive(false);
        }

        return [$username, $password];
    }

    // Left out without interaction, or a blank answer.
    private static function refuseMissingCredentials(SymfonyStyle $io): null
    {
        $io->getErrorStyle()->writeln('The service needs the technician\'s username and password (--username and --password without interaction): the machine was not serviced.');

        return null;
    }

    private function verifiedTechnicianId(SymfonyStyle $io, string $username, #[\SensitiveParameter] string $password): ?string
    {
        try {
            return $this->technicianAuthenticator->authenticate($username, $password);
        } catch (DomainException $refusal) {
            $io->getErrorStyle()->writeln($refusal->getMessage().' The machine was not serviced.');

            return null;
        }
    }

    private static function nullIfBlank(mixed $answer): ?string
    {
        return \is_string($answer) && '' !== $answer ? $answer : null;
    }
}
