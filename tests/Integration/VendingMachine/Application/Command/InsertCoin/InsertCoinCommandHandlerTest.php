<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Application\Command\InsertCoin;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Domain\Coin\UnknownCoinException;
use App\VendingMachine\Domain\VendingMachineRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InsertCoinCommandHandlerTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testAnInsertedCoinReachesTheCreditThroughTheBus(): void
    {
        $container = self::getContainer();

        $container->get(CommandBus::class)->dispatch(new InsertCoinCommand(100));

        self::assertSame(100, $container->get(VendingMachineRepository::class)->get()->wallet()->credit()->total()->cents());
    }

    public function testAnUnknownCoinReachesTheCallerAsItsDomainException(): void
    {
        $this->expectException(UnknownCoinException::class);

        self::getContainer()->get(CommandBus::class)->dispatch(new InsertCoinCommand(50));
    }
}
