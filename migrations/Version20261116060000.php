<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The id of the vending_machine row becomes the machine UUID. The rows stored so far are deleted, so the next load gives the default machine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM vending_machine');
        $this->addSql('ALTER TABLE vending_machine MODIFY id CHAR(36) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM vending_machine');
        $this->addSql('ALTER TABLE vending_machine MODIFY id INT UNSIGNED NOT NULL');
    }
}
