<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Symfony\Messenger\Bus\MessengerCommandBus;
use App\Tests\Integration\Maintenance\HasATechnician;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Query\GetMachine\GetMachineQuery;
use App\VendingMachine\Application\Query\GetMachine\MachineResponse;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class VendingRunCommandTest extends KernelTestCase
{
    use HasATechnician;
    use StartsWithNoStoredMachine;

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

        $exitCode = $tester->execute(['sequence' => [$sequence]], ['capture_stderr_separately' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame($expected."\n", $tester->getDisplay(true));
        self::assertSame('', $tester->getErrorOutput(true));
    }

    public function testReadsASequenceSplitIntoWords(): void
    {
        $tester = self::tester();

        $tester->execute(['sequence' => ['1,', 'GET-WATER']]);

        self::assertSame("WATER, 0.25, 0.10\n", $tester->getDisplay(true));
    }

    public function testReportsARefusalAndGoesOnWithTheCreditKept(): void
    {
        $tester = self::tester();

        $tester->execute(['sequence' => ['0.25, GET-WATER, RETURN-COIN']], ['capture_stderr_separately' => true]);

        self::assertSame("The credit of 25 cents does not cover the price of 65 cents.\n", $tester->getErrorOutput(true));
        self::assertSame("0.25\n", $tester->getDisplay(true));
    }

    public function testRejectsAForeignCoinAndGoesOn(): void
    {
        $tester = self::tester();

        $tester->execute(['sequence' => ['0.50, 1, GET-WATER']], ['capture_stderr_separately' => true]);

        self::assertSame("The machine does not accept a coin of 0.50: it takes 0.05, 0.10, 0.25 and 1.\n", $tester->getErrorOutput(true));
        self::assertSame("WATER, 0.25, 0.10\n", $tester->getDisplay(true));
    }

    public function testLogsTheRejectedCoinsAndActionsAtNotice(): void
    {
        $tester = self::tester();
        $logs = new TestHandler();
        self::getContainer()->get(LoggerInterface::class)->pushHandler($logs);

        $tester->execute(['sequence' => ['0.50, GET-, RETURN-COIN']]);

        $rejections = array_filter($logs->getRecords(), static fn (LogRecord $record): bool => str_starts_with($record->message, 'Rejected'));
        self::assertSame([
            [Level::Notice, 'Rejected "0.50": The machine does not accept a coin of 0.50: it takes 0.05, 0.10, 0.25 and 1.'],
            [Level::Notice, 'Rejected "GET-": Unknown action "GET-": insert 0.05, 0.10, 0.25 or 1, GET-<SELECTOR> or RETURN-COIN.'],
        ], array_values(array_map(static fn (LogRecord $record): array => [$record->level, $record->message], $rejections)));
    }

    public function testCollectsTheTrayAfterPrintingIt(): void
    {
        $tester = self::tester();
        $tester->execute(['sequence' => ['0.10, RETURN-COIN']]);

        $tester->execute(['sequence' => ['0.25, RETURN-COIN']]);

        self::assertSame("0.25\n", $tester->getDisplay(true));
    }

    public function testTheSessionStartsByListingTheProducts(): void
    {
        $tester = self::tester();
        $tester->setInputs(['QUIT']);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('WATER Water 0.65', $tester->getDisplay(true));
        self::assertStringContainsString('JUICE Juice 1.00', $tester->getDisplay(true));
        self::assertStringContainsString('SODA  Soda  1.50', $tester->getDisplay(true));
    }

    public function testTheSessionPrintsAndCollectsTheTrayAfterEachAction(): void
    {
        $tester = self::tester();
        $tester->setInputs(['0.10', 'RETURN-COIN', '1', 'GET-WATER', 'QUIT']);

        $tester->execute([]);

        self::assertSame(['0.10', 'WATER, 0.25, 0.10'], self::trayLines($tester->getDisplay(true)));
    }

    public function testTheSessionAsksAgainAfterABlankLine(): void
    {
        $tester = self::tester();
        $tester->setInputs(['', '0.10', '  ', 'RETURN-COIN', 'QUIT']);

        $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame('', $tester->getErrorOutput(true));
        self::assertSame(['0.10'], self::trayLines($tester->getDisplay(true)));
    }

    public function testTheSessionReportsARefusalAndKeepsTheCredit(): void
    {
        $tester = self::tester();
        $tester->setInputs(['0.25', 'GET-WATER', '0.25', '0.25', 'GET-WATER', 'QUIT']);

        $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame("The credit of 25 cents does not cover the price of 65 cents.\n", $tester->getErrorOutput(true));
        self::assertSame(['WATER, 0.10'], self::trayLines($tester->getDisplay(true)));
    }

    public function testTheSessionRejectsAForeignCoinAndGoesOn(): void
    {
        $tester = self::tester();
        $tester->setInputs(['0.50', '0.25', 'RETURN-COIN', 'QUIT']);

        $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame("The machine does not accept a coin of 0.50: it takes 0.05, 0.10, 0.25 and 1.\n", $tester->getErrorOutput(true));
        self::assertSame(['0.25'], self::trayLines($tester->getDisplay(true)));
    }

    public function testServiceAsksTheUnitsOfEachProductAndTheCountOfEachCoin(): void
    {
        $tester = self::tester();
        $waterAfterARejectedCount = ['-1', '0'];
        $juice = ['2'];
        $sodaKeptAtItsCurrentUnits = [''];
        $coinsFromTheSmallest = ['4', '5', '6', '7'];
        $tester->setInputs(['1', 'SERVICE', self::TECHNICIAN_USERNAME, self::TECHNICIAN_PASSWORD, ...$waterAfterARejectedCount, ...$juice, ...$sodaKeptAtItsCurrentUnits, ...$coinsFromTheSmallest, 'GET-WATER', 'QUIT']);

        $tester->execute([], ['capture_stderr_separately' => true]);

        $status = self::getContainer()->get(QueryBus::class)->query(new GetMachineStatusQuery());
        self::assertInstanceOf(MachineStatusResponse::class, $status);
        self::assertSame(['WATER' => 0, 'JUICE' => 2, 'SODA' => 10], $status->unitsBySelector);
        self::assertSame([5 => 4, 10 => 5, 25 => 6, 100 => 7], $status->coinStockByCents);
        self::assertSame(['1'], self::trayLines($tester->getDisplay(true)));
        self::assertSame("The product of selector WATER is sold out.\n", $tester->getErrorOutput(true));
    }

    public function testTheSessionReportsARefusedServiceAndGoesOn(): void
    {
        $application = new Application(self::bootKernel());
        self::getContainer()->set(CommandBus::class, new ServiceRefusingCommandBus(new MessengerCommandBus(self::getContainer()->get('command.bus'))));
        $tester = new CommandTester($application->find('vending:run'));
        $keepEveryCurrentUnitAndCoinCount = array_fill(0, 7, '');
        $tester->setInputs(['0.25', 'SERVICE', self::TECHNICIAN_USERNAME, self::TECHNICIAN_PASSWORD, ...$keepEveryCurrentUnitAndCoinCount, 'RETURN-COIN', 'QUIT']);

        $exitCode = $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(ServiceRefusingCommandBus::REFUSAL."\n", $tester->getErrorOutput(true));
        self::assertSame(['0.25'], self::trayLines($tester->getDisplay(true)));
    }

    public function testTheSessionReportsWrongCredentialsAndGoesOnWithoutServicing(): void
    {
        $tester = self::tester();
        $tester->setInputs(['0.25', 'SERVICE', self::TECHNICIAN_USERNAME, 'wrong-horse', 'RETURN-COIN', 'QUIT']);

        $exitCode = $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame("Wrong username or password. The machine was not serviced.\n", $tester->getErrorOutput(true));
        self::assertStringNotContainsString('Units of', $tester->getDisplay(true));
        self::assertSame(['0.25'], self::trayLines($tester->getDisplay(true)));
    }

    public function testTheSessionEndsWithTheInput(): void
    {
        $tester = self::tester();
        $tester->setInputs(['1']);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testTheInputEndingDuringServiceLeavesTheMachineUnservicedAndEndsTheSession(): void
    {
        $tester = self::tester();
        $tester->setInputs(['1', 'SERVICE', self::TECHNICIAN_USERNAME, self::TECHNICIAN_PASSWORD, '3']);

        $exitCode = $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame("The input ended before the service was complete: the machine was not serviced.\n", $tester->getErrorOutput(true));
        $queryBus = self::getContainer()->get(QueryBus::class);
        $status = $queryBus->query(new GetMachineStatusQuery());
        self::assertInstanceOf(MachineStatusResponse::class, $status);
        self::assertSame(['WATER' => 10, 'JUICE' => 10, 'SODA' => 10], $status->unitsBySelector);
        $machine = $queryBus->query(new GetMachineQuery());
        self::assertInstanceOf(MachineResponse::class, $machine);
        self::assertSame(100, $machine->creditCents);
    }

    public function testWithoutASequenceOrInteractiveInputItReportsTheMisuseAndFails(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute([], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame('', $tester->getDisplay(true));
        self::assertSame("Give a sequence of actions, such as \"1, GET-WATER\": the interactive session needs input, which -n and -q turn off.\n", $tester->getErrorOutput(true));
    }

    /**
     * The tray lines are selectors and coins separated by commas, alone on their line: the product
     * table, the help line and the prompts all have other words or spacing, so they never match.
     *
     * @return list<string>
     */
    private static function trayLines(string $display): array
    {
        $selectorOrCoin = '(?:[A-Z_0-9]+|\d+(?:\.\d+)?)';
        preg_match_all(\sprintf('/^%1$s(?:, %1$s)*$/m', $selectorOrCoin), $display, $matches);

        return $matches[0];
    }

    private static function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('vending:run'));
    }
}
