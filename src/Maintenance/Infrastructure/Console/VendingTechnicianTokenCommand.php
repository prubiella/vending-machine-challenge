<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Console;

use App\Maintenance\Infrastructure\Security\TechnicianTokenIssuer;
use App\Shared\Domain\DomainException;
use App\Shared\Infrastructure\Console\PasswordQuestion;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'vending:technician:token',
    description: 'Print a technician\'s token for the HTTP API, and nothing else on stdout, asking the credentials the options leave out',
)]
final readonly class VendingTechnicianTokenCommand
{
    public function __construct(
        private TechnicianTokenIssuer $tokenIssuer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('The technician\'s username')]
        ?string $username = null,
        #[Option('The technician\'s password (asked hidden when left out: an option lands in the shell history)')]
        #[\SensitiveParameter]
        ?string $password = null,
    ): int {
        // The prompts and the refusals go to stderr, so `token=$(...)` captures the token alone.
        $stderr = $io->getErrorStyle();
        try {
            $username = self::givenOrAsked($username, 'username', static fn (): mixed => $stderr->ask('Username'));
            $password = self::givenOrAsked($password, 'password', static fn (): mixed => $stderr->askQuestion(PasswordQuestion::hidden('Password')));
            $token = $this->tokenIssuer->issue($username, $password);
        } catch (DomainException|MissingValueException $refusal) {
            $stderr->writeln($refusal->getMessage());

            return Command::FAILURE;
        }

        $io->writeln($token);

        return Command::SUCCESS;
    }

    /**
     * @param \Closure(): mixed $ask
     */
    private static function givenOrAsked(?string $option, string $optionName, \Closure $ask): string
    {
        $value = $option ?? $ask();
        if (!\is_string($value) || '' === $value) {
            throw MissingValueException::forOption($optionName);
        }

        return $value;
    }
}
