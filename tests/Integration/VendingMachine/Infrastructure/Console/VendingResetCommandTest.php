<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Console;

use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\VendingMachineRepository;
use App\VendingMachine\Infrastructure\Persistence\DefaultVendingMachineFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class VendingResetCommandTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testPutsTheDefaultMachineBack(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('vending:reset'));
        $repository = self::getContainer()->get(VendingMachineRepository::class);
        $machine = $repository->get();
        $machine->insertCoin(Coin::OneHundredCents);
        $repository->save($machine);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame("The machine is back to its default products, stock and change.\n", $tester->getDisplay(true));
        self::assertEquals(self::getContainer()->get(DefaultVendingMachineFactory::class)->create(), $repository->get());
    }
}
