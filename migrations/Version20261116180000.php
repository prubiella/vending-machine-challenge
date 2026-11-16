<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The technicians table: one row per technician, with a unique username, a unique email and the hash of its password.';
    }

    // MySQL commits a CREATE TABLE on its own, so a transaction around it could not roll it back.
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE technicians (
                id CHAR(36) NOT NULL,
                username VARCHAR(32) NOT NULL,
                email VARCHAR(254) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX technicians_username (username),
                UNIQUE INDEX technicians_email (email)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE technicians');
    }
}
