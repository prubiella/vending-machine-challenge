<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance\Infrastructure\Console;

use App\Tests\Integration\Maintenance\HasATechnician;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Monolog\Handler\TestHandler;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

final class VendingTechnicianTokenCommandTest extends KernelTestCase
{
    use HasATechnician;

    public function testPrintsOnlyTheTokenOfTheTechnicianWithThoseCredentials(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(
            ['--username' => self::TECHNICIAN_USERNAME, '--password' => self::TECHNICIAN_PASSWORD],
            ['interactive' => false, 'capture_stderr_separately' => true],
        );

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay(true);
        self::assertSame(1, substr_count($output, "\n"));
        $claims = self::getContainer()->get(JWTEncoderInterface::class)->decode(trim($output));
        self::assertSame(self::TECHNICIAN_ID, $claims['sub'] ?? null);
        self::assertSame(['ROLE_TECHNICIAN'], $claims['roles'] ?? null);
    }

    public function testAsksTheCredentialsOnStderrSoStdoutHoldsOnlyTheToken(): void
    {
        $tester = self::tester();
        $tester->setInputs([self::TECHNICIAN_USERNAME, self::TECHNICIAN_PASSWORD]);

        $exitCode = $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Username', $tester->getErrorOutput(true));
        self::assertStringContainsString('Password', $tester->getErrorOutput(true));
        self::assertStringNotContainsString(self::TECHNICIAN_PASSWORD, $tester->getErrorOutput(true));
        self::assertMatchesRegularExpression('/^[\w-]+\.[\w-]+\.[\w-]+\n$/', $tester->getDisplay(true));
    }

    public function testRefusesWrongCredentialsOnStderr(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(
            ['--username' => self::TECHNICIAN_USERNAME, '--password' => 'wrong-horse'],
            ['interactive' => false, 'capture_stderr_separately' => true],
        );

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame('', $tester->getDisplay(true));
        self::assertSame("Wrong username or password.\n", $tester->getErrorOutput(true));
    }

    public function testWithoutInteractionRefusesMissingCredentials(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => self::TECHNICIAN_USERNAME], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame('', $tester->getDisplay(true));
        self::assertSame("Give the technician's password with --password.\n", $tester->getErrorOutput(true));
    }

    public function testTheConsoleLogHidesThePasswordOption(): void
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);
        $logs = new TestHandler();
        self::getContainer()->get('monolog.logger.console')->pushHandler($logs);

        new ApplicationTester($application)->run(
            ['command' => 'vending:technician:token', '--username' => self::TECHNICIAN_USERNAME, '--password' => 'wrong-horse'],
            ['interactive' => false, 'capture_stderr_separately' => true],
        );

        $logged = json_encode(array_map(static fn ($record): array => $record->toArray(), $logs->getRecords()), \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('vending:technician:token', $logged);
        self::assertStringContainsString('--password=***', $logged);
        self::assertStringNotContainsString('wrong-horse', $logged);
    }

    private static function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('vending:technician:token'));
    }
}
