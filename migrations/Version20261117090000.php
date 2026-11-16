<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The machine lives only in MongoDB (ADR 21): drops the tables of the removed MySQL machine adapter, vending_machine and its outbox.';
    }

    // MySQL commits a DROP TABLE on its own, so a transaction around it could not roll it back.
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE vending_machine_outbox');
        $this->addSql('DROP TABLE vending_machine');
    }

    // Both tables as the earlier migrations left them.
    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE vending_machine (
                id CHAR(36) NOT NULL,
                version INT UNSIGNED NOT NULL,
                inventory JSON NOT NULL,
                wallet_credit JSON NOT NULL,
                wallet_stock JSON NOT NULL,
                wallet_cash_box JSON NOT NULL DEFAULT (JSON_OBJECT()),
                tray JSON NOT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE vending_machine_outbox (
                id CHAR(36) NOT NULL,
                machine_id CHAR(36) NOT NULL,
                type VARCHAR(64) NOT NULL,
                occurred_at DATETIME(6) NOT NULL,
                payload JSON NOT NULL,
                PRIMARY KEY (id),
                INDEX vending_machine_outbox_machine (machine_id, occurred_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE = InnoDB
            SQL);
    }
}
