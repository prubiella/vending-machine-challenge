<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116004138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The vending_machine table: one row per machine, its parts as JSON and a version for optimistic locking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE vending_machine (
                id INT UNSIGNED NOT NULL,
                version INT UNSIGNED NOT NULL,
                inventory JSON NOT NULL,
                wallet_credit JSON NOT NULL,
                wallet_stock JSON NOT NULL,
                tray JSON NOT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE vending_machine');
    }
}
