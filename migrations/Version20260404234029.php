<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Complete the panier/reservation structure migration.
 */
final class Version20260404234029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Complete panier and reservation table structure for Symfony';
    }

    public function up(Schema $schema): void
    {
        // Create messenger_messages table if not exists
        $this->addSql('CREATE TABLE IF NOT EXISTS messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');

        // Add foreign key from panier to lieu_touristique
        $this->addSql('ALTER TABLE panier ADD CONSTRAINT FK_24CC0DF2A477615B FOREIGN KEY (id_lieu) REFERENCES lieu_touristique (id_lieu) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_24CC0DF2A477615B ON panier (id_lieu)');

        // Update reservation column types
        $this->addSql('ALTER TABLE reservation CHANGE statut_paiement statut_paiement VARCHAR(50) DEFAULT \'En cours de paiement\' NOT NULL');
        $this->addSql('ALTER TABLE reservation CHANGE code_confirmation code_confirmation VARCHAR(50) NOT NULL');

        // Update foreign key on reservation to cascade delete
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849552FBB81F');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849552FBB81F FOREIGN KEY (id_panier) REFERENCES panier (id_panier) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
        $this->addSql('ALTER TABLE panier DROP FOREIGN KEY FK_24CC0DF2A477615B');
        $this->addSql('DROP INDEX IDX_24CC0DF2A477615B ON panier');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849552FBB81F');
        $this->addSql('ALTER TABLE reservation CHANGE statut_paiement statut_paiement VARCHAR(30) NOT NULL');
        $this->addSql('ALTER TABLE reservation CHANGE code_confirmation code_confirmation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849552FBB81F FOREIGN KEY (id_panier) REFERENCES panier (id_panier)');
    }
}
