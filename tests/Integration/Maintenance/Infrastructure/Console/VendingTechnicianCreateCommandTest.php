<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance\Infrastructure\Console;

use App\Maintenance\Domain\Technician\Technician;
use App\Maintenance\Domain\Technician\TechnicianRepository;
use App\Maintenance\Domain\Technician\Username;
use App\Tests\Integration\Maintenance\StartsWithNoTechnicians;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\Uid\Uuid;

final class VendingTechnicianCreateCommandTest extends KernelTestCase
{
    use StartsWithNoTechnicians;

    public function testCreatesTheTechnicianTheOptionsGiveWithoutAsking(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => 'ana', '--email' => 'Ana@Example.com', '--password' => 'correct-horse'], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $technician = self::storedTechnician('ana');
        self::assertSame('ana@example.com', $technician->email()->value());
        self::assertSame(\sprintf("Technician ana created with id %s.\n", $technician->id()->value()), $tester->getDisplay(true));
        self::assertTrue(Uuid::isValid($technician->id()->value()));
        self::assertPasswordIs('correct-horse', $technician);
    }

    public function testAsksTheUsernameTheEmailAndThePasswordTheOptionsLeaveOut(): void
    {
        $tester = self::tester();
        $tester->setInputs(['ana', 'ana@example.com', 'correct-horse']);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Username', $tester->getDisplay(true));
        self::assertStringContainsString('Email', $tester->getDisplay(true));
        self::assertSame('ana@example.com', self::storedTechnician('ana')->email()->value());
        self::assertStringContainsString('Password', $tester->getDisplay(true));
        self::assertStringNotContainsString('correct-horse', $tester->getDisplay(true));
        self::assertPasswordIs('correct-horse', self::storedTechnician('ana'));
    }

    public function testWithoutInteractionRefusesAMissingPassword(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => 'ana', '--email' => 'ana@example.com'], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("Give the technician's password with --password.\n", $tester->getErrorOutput(true));
        self::assertNull(self::technicians()->byUsername(Username::fromString('ana')));
    }

    public function testRefusesAUsernameAlreadyTaken(): void
    {
        self::tester()->execute(['--username' => 'ana', '--email' => 'ana@example.com', '--password' => 'correct-horse'], ['interactive' => false]);
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => 'ana', '--email' => 'other@example.com', '--password' => 'another-horse'], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("A technician with that username already exists.\n", $tester->getErrorOutput(true));
        self::assertPasswordIs('correct-horse', self::storedTechnician('ana'));
    }

    public function testWithoutInteractionRefusesAMissingEmail(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => 'ana', '--password' => 'correct-horse'], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("Give the technician's email with --email.\n", $tester->getErrorOutput(true));
        self::assertNull(self::technicians()->byUsername(Username::fromString('ana')));
    }

    public function testRefusesAnEmailAlreadyTaken(): void
    {
        self::tester()->execute(['--username' => 'ana', '--email' => 'ana@example.com', '--password' => 'correct-horse'], ['interactive' => false]);
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => 'bob', '--email' => 'ANA@example.com', '--password' => 'correct-horse'], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("A technician with that email already exists.\n", $tester->getErrorOutput(true));
        self::assertNull(self::technicians()->byUsername(Username::fromString('bob')));
    }

    public function testRefusesAShortPassword(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => 'ana', '--email' => 'ana@example.com', '--password' => 'short'], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("A password has at least 8 characters.\n", $tester->getErrorOutput(true));
        self::assertNull(self::technicians()->byUsername(Username::fromString('ana')));
    }

    private static function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('vending:technician:create'));
    }

    private static function technicians(): TechnicianRepository
    {
        return self::getContainer()->get(TechnicianRepository::class);
    }

    private static function storedTechnician(string $username): Technician
    {
        return self::technicians()->byUsername(Username::fromString($username))
            ?? self::fail(\sprintf('No technician named %s is stored.', $username));
    }

    private static function assertPasswordIs(string $password, Technician $technician): void
    {
        self::assertTrue(new NativePasswordHasher()->verify($technician->password()->value(), $password));
    }
}
