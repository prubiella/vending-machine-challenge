<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence;

use App\VendingMachine\Infrastructure\Persistence\MongoVendingMachineRepository;
use MongoDB\Database;

final class MongoVendingMachineRepositoryTest extends VendingMachineRepositoryContractTestCase
{
    public function testStoresTheMachineInTheDocumentOfItsId(): void
    {
        $repository = self::repository();

        $repository->save($repository->get());

        self::assertSame(
            1,
            self::getContainer()->get(Database::class)->getCollection('vending_machines')->countDocuments(['_id' => self::configuredMachineId()]),
        );
    }

    public function testResettingAlsoRemovesTheMachineStoredBeforeItHadAUuid(): void
    {
        $machines = self::getContainer()->get(Database::class)->getCollection('vending_machines');
        $machines->insertOne(['_id' => 1, 'version' => 1]);

        self::repository()->reset();

        self::assertSame(0, $machines->countDocuments(['_id' => 1]));
    }

    protected static function repository(): MongoVendingMachineRepository
    {
        return self::getContainer()->get(MongoVendingMachineRepository::class);
    }
}
