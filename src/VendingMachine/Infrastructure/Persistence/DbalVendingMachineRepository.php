<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence;

use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\VendingMachineRepository;
use Doctrine\DBAL\Connection;

final readonly class DbalVendingMachineRepository implements VendingMachineRepository
{
    private const int MACHINE_ID = 1;

    public function __construct(
        private Connection $connection,
        private VendingMachineMapper $mapper,
        private DefaultVendingMachineFactory $defaultMachine,
    ) {
    }

    public function get(): VendingMachine
    {
        $row = $this->connection->fetchAssociative(
            'SELECT inventory, wallet_credit, wallet_stock, tray FROM vending_machine WHERE id = ?',
            [self::MACHINE_ID],
        );

        return false === $row ? $this->defaultMachine->create() : $this->mapper->fromRow($row);
    }

    public function save(VendingMachine $machine): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO vending_machine (id, version, inventory, wallet_credit, wallet_stock, tray)
                VALUES (:id, 1, :inventory, :wallet_credit, :wallet_stock, :tray) AS saved
                ON DUPLICATE KEY UPDATE
                    version = vending_machine.version + 1,
                    inventory = saved.inventory,
                    wallet_credit = saved.wallet_credit,
                    wallet_stock = saved.wallet_stock,
                    tray = saved.tray
                SQL,
            ['id' => self::MACHINE_ID, ...$this->mapper->toRow($machine)],
        );
    }

    public function reset(): void
    {
        $this->connection->delete('vending_machine', ['id' => self::MACHINE_ID]);
    }
}
