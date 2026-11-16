<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The coins of the cash box in the vending_machine row; a machine stored before it has an empty one.';
    }

    // MySQL commits an ALTER TABLE on its own, so a transaction around it could not roll it back.
    public function isTransactional(): bool
    {
        return false;
    }

    // MySQL takes no literal default on a JSON column: the default is an expression.
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vending_machine ADD wallet_cash_box JSON NOT NULL DEFAULT (JSON_OBJECT()) AFTER wallet_stock');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vending_machine DROP COLUMN wallet_cash_box');
    }
}
