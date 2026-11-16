<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Console;

use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticatedTechnicianResponse;
use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticateTechnicianQuery;
use App\Maintenance\Infrastructure\Security\TechnicianTokenIssuer;
use App\Shared\Application\Bus\QueryBus;
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
        private QueryBus $queryBus,
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
            $technician = $this->queryBus->query(new AuthenticateTechnicianQuery(
                self::givenOrAsked($username, 'username', static fn (): mixed => $stderr->ask('Username')),
                self::givenOrAsked($password, 'password', static fn (): mixed => $stderr->askQuestion(PasswordQuestion::hidden('Password'))),
            ));
            \assert($technician instanceof AuthenticatedTechnicianResponse);
            $token = $this->tokenIssuer->issueFor($technician);
        } catch (DomainException|MissingValueException $refusal) {
            $stderr->writeln($refusal->getMessage());

            return Command::FAILURE;
        }

        $io->writeln($token);

        return Command::SUCCESS;
    }

    /**
     * @param \Closure(): mixed $askTheTechnician
     */
    private static function givenOrAsked(?string $givenValue, string $option, \Closure $askTheTechnician): string
    {
        $value = $givenValue ?? $askTheTechnician();
        if (!\is_string($value) || '' === $value) {
            throw MissingValueException::forOption($option);
        }

        return $value;
    }
}
