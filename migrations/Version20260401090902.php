<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401090902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Normalize FK/index names to Doctrine naming expected by current mappings.
        $this->addSql('ALTER TABLE lieu_touristique DROP FOREIGN KEY `FK_7ECF501F1DC2A166`');
        $this->addSql('ALTER TABLE lieu_touristique DROP FOREIGN KEY `FK_7ECF501FC9486A13`');
        $this->addSql('DROP INDEX fk_lieu_categorie ON lieu_touristique');
        $this->addSql('CREATE INDEX IDX_7ECF501FC9486A13 ON lieu_touristique (id_categorie)');
        $this->addSql('DROP INDEX fk_lieu_adresse ON lieu_touristique');
        $this->addSql('CREATE INDEX IDX_7ECF501F1DC2A166 ON lieu_touristique (id_adresse)');
        $this->addSql('ALTER TABLE lieu_touristique ADD CONSTRAINT `FK_7ECF501F1DC2A166` FOREIGN KEY (id_adresse) REFERENCES adresse (id_adresse)');
        $this->addSql('ALTER TABLE lieu_touristique ADD CONSTRAINT `FK_7ECF501FC9486A13` FOREIGN KEY (id_categorie) REFERENCES categorie (id_categorie)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lieu_touristique DROP FOREIGN KEY `FK_7ECF501F1DC2A166`');
        $this->addSql('ALTER TABLE lieu_touristique DROP FOREIGN KEY `FK_7ECF501FC9486A13`');
        $this->addSql('DROP INDEX idx_7ecf501f1dc2a166 ON lieu_touristique');
        $this->addSql('CREATE INDEX fk_lieu_adresse ON lieu_touristique (id_adresse)');
        $this->addSql('DROP INDEX idx_7ecf501fc9486a13 ON lieu_touristique');
        $this->addSql('CREATE INDEX fk_lieu_categorie ON lieu_touristique (id_categorie)');
        $this->addSql('ALTER TABLE lieu_touristique ADD CONSTRAINT `FK_7ECF501F1DC2A166` FOREIGN KEY (id_adresse) REFERENCES adresse (id_adresse)');
        $this->addSql('ALTER TABLE lieu_touristique ADD CONSTRAINT `FK_7ECF501FC9486A13` FOREIGN KEY (id_categorie) REFERENCES categorie (id_categorie)');
    }
}
