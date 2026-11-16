<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance\Infrastructure\Console;

use App\Maintenance\Domain\Technician\Technician;
use App\Maintenance\Domain\Technician\TechnicianRepository;
use App\Maintenance\Domain\Technician\Username;
use App\Shared\Application\Uuid\UuidGenerator;
use App\Tests\Integration\Maintenance\FixedUuidGenerator;
use App\Tests\Integration\Maintenance\StartsWithNoTechnicians;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

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
        self::assertSame(\sprintf("Technician ana created with id %s.\n", FixedUuidGenerator::ID), $tester->getDisplay(true));
        self::assertSame(FixedUuidGenerator::ID, $technician->id()->value());
        self::assertPasswordIs('correct-horse', $technician);
    }

    public function testAsksTheUsernameTheEmailAndThePasswordTheOptionsLeaveOut(): void
    {
        $tester = self::tester();
        $tester->setInputs(['ana', 'ana@example.com', 'correct-horse']);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay(true);
        self::assertStringContainsString('Username', $display);
        self::assertStringContainsString('Email', $display);
        self::assertStringContainsString('Password', $display);
        self::assertStringNotContainsString('correct-horse', $display);
        $technician = self::storedTechnician('ana');
        self::assertSame('ana@example.com', $technician->email()->value());
        self::assertPasswordIs('correct-horse', $technician);
    }

    public function testKeepsTheSpacesAroundAnAskedPassword(): void
    {
        $tester = self::tester();
        $tester->setInputs(['  correct-horse  ']);

        $exitCode = $tester->execute(['--username' => 'ana', '--email' => 'ana@example.com']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertPasswordIs('  correct-horse  ', self::storedTechnician('ana'));
    }

    public function testAsksAgainForEachValueTheDomainRefuses(): void
    {
        $tester = self::tester();
        $tester->setInputs(['Ana', 'ana', 'ana.example.com', 'ana@example.com', 'short', 'correct-horse']);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay(true);
        self::assertStringContainsString('A username has 3 to 32 lowercase letters', $display);
        self::assertStringContainsString('An email is an address such as ana@example.com.', $display);
        self::assertStringContainsString('A password has at least 8 characters.', $display);
        self::assertPasswordIs('correct-horse', self::storedTechnician('ana'));
    }

    public function testGivesUpAfterThreeRefusedAnswers(): void
    {
        $tester = self::tester();
        $tester->setInputs(['A', 'B', 'C', 'ana']);

        $exitCode = $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("A username has 3 to 32 lowercase letters, digits, dots, hyphens or underscores.\n", $tester->getErrorOutput(true));
        self::assertNull(self::technicians()->byUsername(Username::fromString('ana')));
    }

    public function testRefusesAGivenOptionAtOnceWithoutAskingAgain(): void
    {
        $tester = self::tester();
        $tester->setInputs(['ana', 'ana@example.com', 'correct-horse']);

        $exitCode = $tester->execute(['--username' => 'Ana'], ['capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("A username has 3 to 32 lowercase letters, digits, dots, hyphens or underscores.\n", $tester->getErrorOutput(true));
        self::assertStringNotContainsString('Username', $tester->getDisplay(true));
        self::assertNull(self::technicians()->byUsername(Username::fromString('ana')));
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
        $tester = self::tester(FixedUuidGenerator::OTHER_ID);

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
        $tester = self::tester(FixedUuidGenerator::OTHER_ID);

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

    private static function tester(string $id = FixedUuidGenerator::ID): CommandTester
    {
        $application = new Application(self::bootKernel());
        self::getContainer()->set(UuidGenerator::class, new FixedUuidGenerator($id));

        return new CommandTester($application->find('vending:technician:create'));
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
