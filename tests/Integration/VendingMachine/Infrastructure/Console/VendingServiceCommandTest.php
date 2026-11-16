<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Console;

use App\Shared\Application\Bus\Command as BusCommand;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Symfony\Messenger\Bus\MessengerCommandBus;
use App\Tests\Integration\Maintenance\HasATechnician;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\CollectTray\CollectTrayCommand;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
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
    use HasATechnician;
    use StartsWithNoStoredMachine;

    private const string MISSING_CREDENTIALS = "The service needs the technician's username and password (--username and --password without interaction): the machine was not serviced.\n";
    private const array CREDENTIALS = ['--username' => self::TECHNICIAN_USERNAME, '--password' => self::TECHNICIAN_PASSWORD];

    private const array DEFAULT_UNITS = ['WATER' => 10, 'JUICE' => 10, 'SODA' => 10];
    private const array DEFAULT_COINS = [5 => 10, 10 => 10, 25 => 10, 100 => 0];

    public function testServicesTheMachineWithTheGivenOptionsWithoutAsking(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute([
            ...self::CREDENTIALS,
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

        $exitCode = $tester->execute([...self::CREDENTIALS, '--units' => ['WATER:1'], '--coins' => ['1:3']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Units of JUICE [10]', $tester->getDisplay(true));
        self::assertStringNotContainsString('Units of WATER', $tester->getDisplay(true));
        self::assertStatus(['WATER' => 1, 'JUICE' => 4, 'SODA' => 10], [5 => 6, 10 => 10, 25 => 8, 100 => 3]);
    }

    public function testWithoutInteractionKeepsTheCurrentValuesTheOptionsLeaveOut(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute([...self::CREDENTIALS, '--units' => ['SODA:4']], ['interactive' => false]);

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

        $exitCode = $tester->execute(self::CREDENTIALS, ['interactive' => false]);

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

        $exitCode = $tester->execute(self::CREDENTIALS, ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame("1\nThe machine is serviced.\n", $tester->getDisplay(true));
        $tray = self::getContainer()->get(QueryBus::class)->query(new GetTrayContentsQuery());
        self::assertEquals(new TrayContentsResponse([], []), $tray);
    }

    public function testTheInputEndingLeavesTheMachineUnserviced(): void
    {
        $tester = self::tester();
        $tester->setInputs(['3']);

        $exitCode = $tester->execute(self::CREDENTIALS, ['capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("The input ended before the service was complete: the machine was not serviced.\n", $tester->getErrorOutput(true));
        self::assertStatus(self::DEFAULT_UNITS, self::DEFAULT_COINS);
    }

    public function testReportsARefusedServiceAndFails(): void
    {
        $application = new Application(self::bootKernel());
        self::getContainer()->set(CommandBus::class, new ServiceRefusingCommandBus(new MessengerCommandBus(self::getContainer()->get('command.bus'))));
        $tester = new CommandTester($application->find('vending:service'));

        $exitCode = $tester->execute([...self::CREDENTIALS, '--units' => ['WATER:2']], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(ServiceRefusingCommandBus::REFUSAL."\n", $tester->getErrorOutput(true));
        self::assertSame('', $tester->getDisplay(true));
        self::assertStatus(self::DEFAULT_UNITS, self::DEFAULT_COINS);
    }

    public function testRejectsAnUnknownSelectorBeforeAskingAnything(): void
    {
        $tester = self::tester();
        $tester->setInputs(array_fill(0, 7, ''));

        try {
            $tester->execute(['--units' => ['WATER:2', 'CHIPS:3']]);
            self::fail('Expected an InvalidOptionException.');
        } catch (InvalidOptionException $e) {
            self::assertSame('Give --units a product the machine has, such as WATER:5, got CHIPS.', $e->getMessage());
        }

        self::assertStringNotContainsString('Username', $tester->getDisplay(true));
        self::assertStringNotContainsString('Units of', $tester->getDisplay(true));
        self::assertStatus(self::DEFAULT_UNITS, self::DEFAULT_COINS);
    }

    public function testAsksTheCredentialsTheOptionsLeaveOutWithThePasswordHidden(): void
    {
        $tester = self::tester();
        $tester->setInputs([self::TECHNICIAN_USERNAME, self::TECHNICIAN_PASSWORD]);

        $exitCode = $tester->execute(['--units' => ['WATER:2', 'JUICE:2', 'SODA:2'], '--coins' => ['0.05:1', '0.10:1', '0.25:1', '1:0']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Username', $tester->getDisplay(true));
        self::assertStringContainsString('Password', $tester->getDisplay(true));
        self::assertStringNotContainsString(self::TECHNICIAN_PASSWORD, $tester->getDisplay(true));
        self::assertStatus(['WATER' => 2, 'JUICE' => 2, 'SODA' => 2], [5 => 1, 10 => 1, 25 => 1, 100 => 0]);
    }

    public function testServicesTheMachineInTheNameOfTheAuthenticatedTechnician(): void
    {
        $application = new Application(self::bootKernel());
        $recordingBus = new class(new MessengerCommandBus(self::getContainer()->get('command.bus'))) implements CommandBus {
            /** @var list<BusCommand> */
            public array $dispatched = [];

            public function __construct(private readonly CommandBus $commandBus)
            {
            }

            public function dispatch(BusCommand $command): void
            {
                $this->dispatched[] = $command;
                $this->commandBus->dispatch($command);
            }
        };
        self::getContainer()->set(CommandBus::class, $recordingBus);

        new CommandTester($application->find('vending:service'))->execute(self::CREDENTIALS, ['interactive' => false]);

        $service = $recordingBus->dispatched[0] ?? null;
        self::assertInstanceOf(ServiceMachineCommand::class, $service);
        self::assertSame(self::TECHNICIAN_ID, $service->technicianId);
    }

    public function testRefusesAWrongPasswordAndLeavesTheMachineUnserviced(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(
            ['--username' => self::TECHNICIAN_USERNAME, '--password' => 'wrong-horse', '--units' => ['WATER:2']],
            ['interactive' => false, 'capture_stderr_separately' => true],
        );

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("Wrong username or password. The machine was not serviced.\n", $tester->getErrorOutput(true));
        self::assertStatus(self::DEFAULT_UNITS, self::DEFAULT_COINS);
    }

    public function testWithoutInteractionRefusesMissingCredentials(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--username' => self::TECHNICIAN_USERNAME, '--units' => ['WATER:2']], ['interactive' => false, 'capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(self::MISSING_CREDENTIALS, $tester->getErrorOutput(true));
        self::assertStatus(self::DEFAULT_UNITS, self::DEFAULT_COINS);
    }

    public function testTheInputEndingBeforeTheUsernameLeavesTheMachineUnserviced(): void
    {
        $tester = self::tester();

        $exitCode = $tester->execute(['--units' => ['WATER:2']], ['capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame("The input ended before the service was complete: the machine was not serviced.\n", $tester->getErrorOutput(true));
        self::assertStatus(self::DEFAULT_UNITS, self::DEFAULT_COINS);
    }

    // A hidden answer cannot tell the input ending from an empty password: both leave it empty.
    public function testAnEmptyPasswordLeavesTheMachineUnserviced(): void
    {
        $tester = self::tester();
        $tester->setInputs([self::TECHNICIAN_USERNAME, '']);

        $exitCode = $tester->execute(['--units' => ['WATER:2']], ['capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(self::MISSING_CREDENTIALS, $tester->getErrorOutput(true));
        self::assertStatus(self::DEFAULT_UNITS, self::DEFAULT_COINS);
    }

    public function testRejectsAnOptionThatIsNotAPairWithACount(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give --units as KEY:COUNT, such as WATER:5 or 0.25:10, got "WATER".');

        self::tester()->execute([...self::CREDENTIALS, '--units' => ['WATER']], ['interactive' => false]);
    }

    public function testRejectsAProductGivenTwice(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give --units once for each key, got WATER twice.');

        self::tester()->execute([...self::CREDENTIALS, '--units' => ['WATER:2', 'WATER:5']], ['interactive' => false]);
    }

    public function testRejectsACoinGivenTwice(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give --coins once for each key, got 0.10 twice.');

        self::tester()->execute([...self::CREDENTIALS, '--coins' => ['0.10:5', '0.10:8']], ['interactive' => false]);
    }

    public function testRejectsAForeignCoin(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give --coins a coin the machine takes, such as 0.25:10, got 0.50.');

        self::tester()->execute([...self::CREDENTIALS, '--coins' => ['0.50:3']], ['interactive' => false]);
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
