<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\QueryBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use App\VendingMachine\Infrastructure\Console\RejectedTokenException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Tester\CommandTester;

final class VendingServiceCommandTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    private const array DEFAULT_UNITS = ['WATER' => 10, 'JUICE' => 10, 'SODA' => 10];
    private const array DEFAULT_COINS = [5 => 10, 10 => 10, 25 => 10, 100 => 0];

    public function testServicesTheMachineWithTheGivenOptionsWithoutAsking(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute([
            '--units' => ['WATER:2', 'JUICE:0', 'SODA:7'],
            '--coins' => ['0.05:1', '0.10:2', '0.25:3', '1:0'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame("The machine is serviced.\n", $tester->getDisplay(true));
        self::assertStatus(['WATER' => 2, 'JUICE' => 0, 'SODA' => 7], [5 => 1, 10 => 2, 25 => 3, 100 => 0]);
    }

    public function testAsksWhatTheOptionsLeaveOutWithTheCurrentValuesAsDefaults(): void
    {
        $tester = self::tester();
        $juiceAfterARejectedCount = ['many', '4'];
        $sodaKeptAtItsCurrentUnits = [''];
        $coinsFromTheSmallestButTheOne = ['6', '', '8'];
        $tester->setInputs([...$juiceAfterARejectedCount, ...$sodaKeptAtItsCurrentUnits, ...$coinsFromTheSmallestButTheOne]);

        $exitCode = $tester->execute(['--units' => ['WATER:1'], '--coins' => ['1:3']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Units of JUICE [10]', $tester->getDisplay(true));
        self::assertStringNotContainsString('Units of WATER', $tester->getDisplay(true));
        self::assertStatus(['WATER' => 1, 'JUICE' => 4, 'SODA' => 10], [5 => 6, 10 => 10, 25 => 8, 100 => 3]);
    }

    public function testWithoutInteractionKeepsTheCurrentValuesTheOptionsLeaveOut(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--units' => ['SODA:4']], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStatus(['WATER' => 10, 'JUICE' => 10, 'SODA' => 4], self::DEFAULT_COINS);
    }

    public function testTheInputEndingLeavesTheMachineUnserviced(): void
    {
        $tester = self::tester();
        $tester->setInputs(['3']);

        $exitCode = $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("The input ended before the service was complete: the machine was not serviced.\n", $tester->getErrorOutput(true));
        self::assertStatus(self::DEFAULT_UNITS, self::DEFAULT_COINS);
    }

    public function testReportsAnUnknownSelectorAndLeavesTheMachineUnserviced(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--units' => ['CHIPS:3']], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("There is no product with selector CHIPS.\n", $tester->getErrorOutput(true));
        self::assertStatus(self::DEFAULT_UNITS, self::DEFAULT_COINS);
    }

    public function testRejectsAnOptionThatIsNotAPairWithACount(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give --units as KEY:COUNT, such as WATER:5 or 0.25:10, got "WATER".');

        self::tester()->execute(['--units' => ['WATER']], ['interactive' => false]);
    }

    public function testRejectsAForeignCoin(): void
    {
        $this->expectException(RejectedTokenException::class);

        self::tester()->execute(['--coins' => ['0.50:3']], ['interactive' => false]);
    }

    /**
     * @param array<string, int> $unitsBySelector
     * @param array<int, int>    $coinStockByCents
     */
    private static function assertStatus(array $unitsBySelector, array $coinStockByCents): void
    {
        $status = self::getContainer()->get(QueryBus::class)->query(new GetMachineStatusQuery());
        self::assertInstanceOf(MachineStatusResponse::class, $status);
        self::assertSame($unitsBySelector, $status->unitsBySelector);
        self::assertSame($coinStockByCents, $status->coinStockByCents);
    }

    private static function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('vending:service'));
    }
}
