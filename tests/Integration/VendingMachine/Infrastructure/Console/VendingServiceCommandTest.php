<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\CollectTray\CollectTrayCommand;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Application\Query\GetMachine\GetMachineQuery;
use App\VendingMachine\Application\Query\GetMachine\MachineResponse;
use App\VendingMachine\Application\Query\GetMachineStatus\GetMachineStatusQuery;
use App\VendingMachine\Application\Query\GetMachineStatus\MachineStatusResponse;
use App\VendingMachine\Application\Query\GetTrayContents\GetTrayContentsQuery;
use App\VendingMachine\Application\Query\GetTrayContents\TrayContentsResponse;
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
        $coinsOf5Cents = ['6'];
        $coinsOf10CentsKeptAtTheirCurrentCount = [''];
        $coinsOf25Cents = ['8'];
        $tester->setInputs([
            ...$juiceAfterARejectedCount,
            ...$sodaKeptAtItsCurrentUnits,
            ...$coinsOf5Cents,
            ...$coinsOf10CentsKeptAtTheirCurrentCount,
            ...$coinsOf25Cents,
        ]);

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

    public function testWithoutInteractionOrOptionsServicesTheMachineWithItsCurrentValues(): void
    {
        $tester = self::tester();
        $commandBus = self::getContainer()->get(CommandBus::class);
        $commandBus->dispatch(new InsertCoinCommand(100));
        $commandBus->dispatch(new SelectProductCommand('WATER'));
        $commandBus->dispatch(new CollectTrayCommand());
        $commandBus->dispatch(new InsertCoinCommand(25));

        $exitCode = $tester->execute([], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame("0.25\nThe machine is serviced.\n", $tester->getDisplay(true));
        self::assertStatus(['WATER' => 9, 'JUICE' => 10, 'SODA' => 10], [5 => 10, 10 => 9, 25 => 9, 100 => 1]);
        $machine = self::getContainer()->get(QueryBus::class)->query(new GetMachineQuery());
        self::assertInstanceOf(MachineResponse::class, $machine);
        self::assertSame(0, $machine->creditCents);
    }

    public function testPrintsAndCollectsTheCreditTheServiceReturns(): void
    {
        $tester = self::tester();
        self::getContainer()->get(CommandBus::class)->dispatch(new InsertCoinCommand(100));

        $exitCode = $tester->execute([], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame("1\nThe machine is serviced.\n", $tester->getDisplay(true));
        $tray = self::getContainer()->get(QueryBus::class)->query(new GetTrayContentsQuery());
        self::assertEquals(new TrayContentsResponse([], []), $tray);
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

    public function testRejectsAProductGivenTwice(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give --units once for each key, got WATER twice.');

        self::tester()->execute(['--units' => ['WATER:2', 'WATER:5']], ['interactive' => false]);
    }

    public function testRejectsACoinGivenTwice(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give --coins once for each key, got 0.10 twice.');

        self::tester()->execute(['--coins' => ['0.10:5', '0.10:8']], ['interactive' => false]);
    }

    public function testRejectsAForeignCoin(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give --coins a coin the machine takes, such as 0.25:10, got 0.50.');

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
