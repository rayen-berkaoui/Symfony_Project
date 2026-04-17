<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notifications table for post/comment activity notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT NOT NULL,
            post_id INT DEFAULT NULL,
            comment_id INT DEFAULT NULL,
            recipient_key VARCHAR(255) NOT NULL,
            actor_key VARCHAR(255) NOT NULL,
            type VARCHAR(64) NOT NULL,
            message LONGTEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_6000B0D3DD7F97D0 (post_id),
            INDEX IDX_6000B0D3F8697D13 (comment_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3DD7F97D0 FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3F8697D13 FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS notifications');
    }
}
