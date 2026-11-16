<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance;

use App\Maintenance\Application\Command\CreateTechnician\CreateTechnicianCommand;
use App\Shared\Application\Bus\CommandBus;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Before;

/**
 * For a KernelTestCase: each test starts with one technician stored, and no other.
 */
trait HasATechnician
{
    private const string TECHNICIAN_ID = '0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61';
    private const string TECHNICIAN_USERNAME = 'ana.tech';
    private const string TECHNICIAN_EMAIL = 'ana.tech@example.com';
    private const string TECHNICIAN_PASSWORD = 'correct-horse';

    #[Before]
    protected function storeOneTechnician(): void
    {
        $container = self::getContainer();
        $container->get(Connection::class)->executeStatement('DELETE FROM technicians');
        $container->get(CommandBus::class)->dispatch(new CreateTechnicianCommand(
            self::TECHNICIAN_ID,
            self::TECHNICIAN_USERNAME,
            self::TECHNICIAN_EMAIL,
            self::TECHNICIAN_PASSWORD,
        ));
    }
}
