<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Console;

use App\Shared\Application\Query\HealthCheck\DatabaseProbe;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class HealthCheckCommandTest extends KernelTestCase
{
    public function testPrintsTheDetailOfEachDependencyAsJson(): void
    {
        $commandTester = self::tester();

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('{"status":"ok","checks":{"database":"ok"}}'.\PHP_EOL, $commandTester->getDisplay());
    }

    public function testSucceedsWhenTheDatabaseIsNotReachable(): void
    {
        $commandTester = self::testerWithUnreachableDatabase();

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('{"status":"ok","checks":{"database":"ko"}}'.\PHP_EOL, $commandTester->getDisplay());
    }

    public function testReadySucceedsWhenEveryDependencyIsOk(): void
    {
        $commandTester = self::tester();

        $exitCode = $commandTester->execute(['--ready' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testReadyFailsWhenTheDatabaseIsNotReachable(): void
    {
        $commandTester = self::testerWithUnreachableDatabase();

        $exitCode = $commandTester->execute(['--ready' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame('{"status":"ok","checks":{"database":"ko"}}'.\PHP_EOL, $commandTester->getDisplay());
    }

    private static function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:health-check'));
    }

    private static function testerWithUnreachableDatabase(): CommandTester
    {
        $tester = self::tester();
        self::getContainer()->set(DatabaseProbe::class, new class implements DatabaseProbe {
            public function isReachable(): bool
            {
                return false;
            }
        });

        return $tester;
    }
}
