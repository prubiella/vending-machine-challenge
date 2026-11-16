<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Bus\CommandBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\VendingMachineRepository;
use App\VendingMachine\Infrastructure\Persistence\DbalVendingMachineRepository;
use App\VendingMachine\Infrastructure\Persistence\DefaultVendingMachineFactory;
use App\VendingMachine\Infrastructure\Persistence\VendingMachineMapper;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RetryOnConcurrentUpdateMiddlewareTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testTheCommandBusRunsACommandAgainWhenAnotherSaveGotInFirst(): void
    {
        $container = self::getContainer();
        $stored = new DbalVendingMachineRepository(
            $container->get(Connection::class),
            new VendingMachineMapper(),
            $container->get(DefaultVendingMachineFactory::class),
        );
        $repository = new class($stored) implements VendingMachineRepository {
            public int $saves = 0;

            public function __construct(private readonly VendingMachineRepository $stored)
            {
            }

            public function get(): VendingMachine
            {
                return $this->stored->get();
            }

            public function save(VendingMachine $machine): void
            {
                if (0 === $this->saves++) {
                    $this->stored->save($this->stored->get());
                }
                $this->stored->save($machine);
            }
        };
        $container->set(VendingMachineRepository::class, $repository);

        $container->get(CommandBus::class)->dispatch(new InsertCoinCommand(100));

        self::assertSame(2, $repository->saves);
        self::assertSame(100, $repository->get()->wallet()->credit()->total()->cents());
    }
}
