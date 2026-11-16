<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence;

use App\VendingMachine\Infrastructure\Persistence\DbalVendingMachineRepository;

final class DbalVendingMachineRepositoryTest extends VendingMachineRepositoryContractTestCase
{
    protected static function repository(): DbalVendingMachineRepository
    {
        return self::getContainer()->get(DbalVendingMachineRepository::class);
    }
}
