<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Application\Command\CreateTechnician;

use App\Maintenance\Application\Command\CreateTechnician\CreateTechnicianCommand;
use App\Maintenance\Application\Command\CreateTechnician\CreateTechnicianCommandHandler;
use App\Maintenance\Domain\Technician\HashedPassword;
use App\Maintenance\Domain\Technician\InvalidUsernameException;
use App\Maintenance\Domain\Technician\PasswordHasher;
use App\Maintenance\Domain\Technician\PlainPassword;
use App\Maintenance\Domain\Technician\TooShortPasswordException;
use App\Maintenance\Domain\Technician\Username;
use App\Shared\Application\Bus\Masking;
use App\Tests\Unit\Maintenance\Application\InMemoryTechnicianRepository;
use PHPUnit\Framework\TestCase;

final class CreateTechnicianCommandHandlerTest extends TestCase
{
    private const string ID = '0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61';

    public function testAddsATechnicianWithTheHashOfItsPassword(): void
    {
        $repository = new InMemoryTechnicianRepository();

        self::handler($repository)(new CreateTechnicianCommand(self::ID, 'ana', 'correct-horse'));

        $technician = $repository->byUsername(Username::fromString('ana'));
        self::assertNotNull($technician);
        self::assertSame(self::ID, $technician->id()->value());
        self::assertSame('hashed:correct-horse', $technician->password()->value());
    }

    public function testRefusesAnInvalidUsername(): void
    {
        $repository = new InMemoryTechnicianRepository();

        try {
            self::handler($repository)(new CreateTechnicianCommand(self::ID, 'A', 'correct-horse'));
            self::fail('An invalid username was accepted.');
        } catch (InvalidUsernameException) {
            self::assertSame(0, $repository->count());
        }
    }

    public function testRefusesAShortPassword(): void
    {
        $repository = new InMemoryTechnicianRepository();

        try {
            self::handler($repository)(new CreateTechnicianCommand(self::ID, 'ana', 'short'));
            self::fail('A short password was accepted.');
        } catch (TooShortPasswordException) {
            self::assertSame(0, $repository->count());
        }
    }

    public function testMasksTheUsernameAndHidesThePasswordInTheLog(): void
    {
        $command = new CreateTechnicianCommand(self::ID, 'ana', 'correct-horse');

        self::assertSame(['username' => Masking::KeepFirstThree, 'password' => Masking::Hide], $command->sensitiveFields());
    }

    private static function handler(InMemoryTechnicianRepository $repository): CreateTechnicianCommandHandler
    {
        return new CreateTechnicianCommandHandler($repository, new class implements PasswordHasher {
            public function hash(PlainPassword $password): HashedPassword
            {
                return HashedPassword::fromString('hashed:'.$password->value());
            }
        });
    }
}
