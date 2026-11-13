<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Console;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class HealthCheckCommandTest extends KernelTestCase
{
    public function testPrintsTheHealthCheckResultAsJson(): void
    {
        $application = new Application(self::bootKernel());
        $commandTester = new CommandTester($application->find('app:health-check'));

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('{"status":"ok"}'.\PHP_EOL, $commandTester->getDisplay());
    }
}
