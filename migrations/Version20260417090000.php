<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add oauth_provider and oauth_id fields to utilisateur table for social login.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD oauth_provider VARCHAR(20) DEFAULT NULL, ADD oauth_id VARCHAR(191) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_UTILISATEUR_OAUTH_PROVIDER ON utilisateur (oauth_provider)');
        $this->addSql('CREATE INDEX IDX_UTILISATEUR_OAUTH_ID ON utilisateur (oauth_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_UTILISATEUR_OAUTH_PROVIDER ON utilisateur');
        $this->addSql('DROP INDEX IDX_UTILISATEUR_OAUTH_ID ON utilisateur');
        $this->addSql('ALTER TABLE utilisateur DROP oauth_provider, DROP oauth_id');
    }
}
