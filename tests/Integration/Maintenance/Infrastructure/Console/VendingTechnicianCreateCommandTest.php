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

        $exitCode = $tester->execute(['--username' => 'ana', '--password' => 'correct-horse'], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $technician = self::storedTechnician('ana');
        self::assertSame(\sprintf("Technician ana created with id %s.\n", $technician->id()->value()), $tester->getDisplay(true));
        self::assertTrue(Uuid::isValid($technician->id()->value()));
        self::assertPasswordIs('correct-horse', $technician);
    }

    public function testAsksTheUsernameAndThePasswordTheOptionsLeaveOut(): void
    {
        $tester = self::tester();
        $tester->setInputs(['ana', 'correct-horse']);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Username', $tester->getDisplay(true));
        self::assertStringContainsString('Password', $tester->getDisplay(true));
        self::assertStringNotContainsString('correct-horse', $tester->getDisplay(true));
        self::assertPasswordIs('correct-horse', self::storedTechnician('ana'));
    }

    public function testWithoutInteractionRefusesAMissingPassword(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => 'ana'], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("Give the technician a password with --password.\n", $tester->getErrorOutput(true));
        self::assertNull(self::technicians()->byUsername(Username::fromString('ana')));
    }

    public function testRefusesAUsernameAlreadyTaken(): void
    {
        self::tester()->execute(['--username' => 'ana', '--password' => 'correct-horse'], ['interactive' => false]);
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => 'ana', '--password' => 'another-horse'], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("A technician named \"ana\" already exists.\n", $tester->getErrorOutput(true));
        self::assertPasswordIs('correct-horse', self::storedTechnician('ana'));
    }

    public function testRefusesAShortPassword(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => 'ana', '--password' => 'short'], ['interactive' => false, 'capture_stderr_separately' => true]);

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
