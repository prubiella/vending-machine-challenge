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
    public function testPrintsTheHealthCheckResultAsJson(): void
    {
        $commandTester = new CommandTester(new Application(self::bootKernel())->find('app:health-check'));

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('{"status":"ok","database":"ok"}'.\PHP_EOL, $commandTester->getDisplay());
    }

    public function testFailsWhenTheDatabaseIsNotReachable(): void
    {
        $application = new Application(self::bootKernel());
        self::getContainer()->set(DatabaseProbe::class, new class implements DatabaseProbe {
            public function isReachable(): bool
            {
                return false;
            }
        });
        $commandTester = new CommandTester($application->find('app:health-check'));

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame('{"status":"error","database":"error"}'.\PHP_EOL, $commandTester->getDisplay());
    }
}
