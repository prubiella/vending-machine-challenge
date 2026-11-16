<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Application\Query\AuthenticateTechnician;

use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticatedTechnicianResponse;
use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticateTechnicianQuery;
use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticateTechnicianQueryHandler;
use App\Maintenance\Domain\Technician\Email;
use App\Maintenance\Domain\Technician\InvalidCredentialsException;
use App\Maintenance\Domain\Technician\PlainPassword;
use App\Maintenance\Domain\Technician\Technician;
use App\Maintenance\Domain\Technician\TechnicianId;
use App\Maintenance\Domain\Technician\Username;
use App\Tests\Unit\Maintenance\Application\InMemoryTechnicianRepository;
use App\Tests\Unit\Maintenance\Domain\PrefixPasswordHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthenticateTechnicianQueryHandlerTest extends TestCase
{
    private const string ID = '0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61';

    public function testGivesTheIdOfTheTechnicianWithThatUsernameAndPassword(): void
    {
        $response = self::handler()(new AuthenticateTechnicianQuery('ana', 'correct-horse'));

        self::assertEquals(new AuthenticatedTechnicianResponse(self::ID), $response);
    }

    #[DataProvider('wrongCredentials')]
    public function testRefusesWrongCredentialsAlike(string $username, string $password): void
    {
        $this->expectExceptionObject(InvalidCredentialsException::wrongUsernameOrPassword());

        self::handler()(new AuthenticateTechnicianQuery($username, $password));
    }

    #[DataProvider('wrongCredentials')]
    public function testDoesTheWorkOfOneVerificationWhateverIsWrong(string $username, string $password): void
    {
        $hasher = new PrefixPasswordHasher();

        try {
            self::handler($hasher)(new AuthenticateTechnicianQuery($username, $password));
        } catch (InvalidCredentialsException) {
        }

        self::assertSame(1, $hasher->verifications);
    }

    /** @return iterable<string, array{string, string}> */
    public static function wrongCredentials(): iterable
    {
        yield 'a wrong password' => ['ana', 'wrong-horse'];
        yield 'an unknown username' => ['bob', 'correct-horse'];
        yield 'a username no technician can have' => ['A', 'correct-horse'];
    }

    private static function handler(PrefixPasswordHasher $hasher = new PrefixPasswordHasher()): AuthenticateTechnicianQueryHandler
    {
        $repository = new InMemoryTechnicianRepository();
        $repository->add(Technician::register(
            TechnicianId::fromString(self::ID),
            Username::fromString('ana'),
            Email::fromString('ana@example.com'),
            $hasher->hash(PlainPassword::fromString('correct-horse')),
        ));

        return new AuthenticateTechnicianQueryHandler($repository, $hasher);
    }
}
