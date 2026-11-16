<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The machine history: one row per relayed event, keyed by the event id so a repeated event is kept once.';
    }

    // MySQL commits a CREATE TABLE on its own, so a transaction around it could not roll it back.
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE machine_history (
                id CHAR(36) NOT NULL,
                machine_id CHAR(36) NOT NULL,
                type VARCHAR(64) NOT NULL,
                occurred_at DATETIME(6) NOT NULL,
                payload JSON NOT NULL,
                PRIMARY KEY (id),
                INDEX machine_history_type (machine_id, type, occurred_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE machine_history');
    }
}
