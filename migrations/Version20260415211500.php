<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415211500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create historique_action table for user action history in dashboard';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE historique_action (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, action_label VARCHAR(255) NOT NULL, route_name VARCHAR(180) DEFAULT NULL, http_method VARCHAR(10) NOT NULL, path_info VARCHAR(255) NOT NULL, status_code INT NOT NULL, details LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_historique_action_created_at (created_at), INDEX idx_historique_action_route_name (route_name), INDEX IDX_D065FD0CFB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE historique_action ADD CONSTRAINT FK_D065FD0CFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE historique_action DROP FOREIGN KEY FK_D065FD0CFB88E14F');
        $this->addSql('DROP TABLE historique_action');
    }
}