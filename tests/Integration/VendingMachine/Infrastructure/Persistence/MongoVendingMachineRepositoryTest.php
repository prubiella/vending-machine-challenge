<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence;

use App\VendingMachine\Infrastructure\Persistence\MongoVendingMachineRepository;

final class MongoVendingMachineRepositoryTest extends VendingMachineRepositoryContractTestCase
{
    protected static function repository(): MongoVendingMachineRepository
    {
        return self::getContainer()->get(MongoVendingMachineRepository::class);
    }
}
