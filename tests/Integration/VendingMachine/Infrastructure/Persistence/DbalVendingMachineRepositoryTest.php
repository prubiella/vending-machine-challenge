<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence;

use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Infrastructure\Persistence\DbalVendingMachineRepository;
use App\VendingMachine\Infrastructure\Persistence\DefaultVendingMachineFactory;
use App\VendingMachine\Infrastructure\Persistence\VendingMachineMapper;
use Doctrine\DBAL\Connection;

final class DbalVendingMachineRepositoryTest extends VendingMachineRepositoryContractTestCase
{
    public function testStoresTheMachineInTheRowOfItsId(): void
    {
        $repository = self::repository();

        $repository->save($repository->get());

        self::assertSame(
            [self::configuredMachineId()],
            self::getContainer()->get(Connection::class)->fetchFirstColumn('SELECT id FROM vending_machine'),
        );
    }

    // The machine is stored in MongoDB, so the container does not wire this adapter.
    protected static function repository(): DbalVendingMachineRepository
    {
        return new DbalVendingMachineRepository(
            self::getContainer()->get(Connection::class),
            new VendingMachineMapper(),
            self::getContainer()->get(DefaultVendingMachineFactory::class),
            MachineId::fromString(self::configuredMachineId()),
        );
    }
}
