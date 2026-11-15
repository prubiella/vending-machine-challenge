<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class VendingRunCommandTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function statementExamples(): iterable
    {
        yield 'buy a soda with the exact price' => ['1, 0.25, 0.25, GET-SODA', 'SODA'];
        yield 'return the inserted coins' => ['0.10, 0.10, RETURN-COIN', '0.10, 0.10'];
        yield 'buy a water and get the change' => ['1, GET-WATER', 'WATER, 0.25, 0.10'];
    }

    #[DataProvider('statementExamples')]
    public function testPrintsTheOutputOfTheStatementExample(string $sequence, string $expected): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['sequence' => $sequence], ['capture_stderr_separately' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame($expected."\n", $tester->getDisplay(true));
        self::assertSame('', $tester->getErrorOutput(true));
    }

    public function testReportsARefusalAndGoesOnWithTheCreditKept(): void
    {
        $tester = self::tester();

        $tester->execute(['sequence' => '0.25, GET-WATER, RETURN-COIN'], ['capture_stderr_separately' => true]);

        self::assertSame("The credit of 25 cents does not cover the price of 65 cents.\n", $tester->getErrorOutput(true));
        self::assertSame("0.25\n", $tester->getDisplay(true));
    }

    public function testRejectsAForeignCoinAndGoesOn(): void
    {
        $tester = self::tester();

        $tester->execute(['sequence' => '0.50, 1, GET-WATER'], ['capture_stderr_separately' => true]);

        self::assertSame("The machine does not accept a coin of 0.50: it takes 0.05, 0.10, 0.25 and 1.\n", $tester->getErrorOutput(true));
        self::assertSame("WATER, 0.25, 0.10\n", $tester->getDisplay(true));
    }

    public function testCollectsTheTrayAfterPrintingIt(): void
    {
        $tester = self::tester();
        $tester->execute(['sequence' => '0.10, RETURN-COIN']);

        $tester->execute(['sequence' => '0.25, RETURN-COIN']);

        self::assertSame("0.25\n", $tester->getDisplay(true));
    }

    private static function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('vending:run'));
    }
}
