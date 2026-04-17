<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow nullable phone numbers for users created via social login.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur MODIFY num_tel VARCHAR(8) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur MODIFY num_tel VARCHAR(8) NOT NULL');
    }
}
