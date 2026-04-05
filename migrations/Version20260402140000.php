<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add chat_messages.user_key if missing (align with ChatMessage entity).';
    }

    public function up(Schema $schema): void
    {
        $exists = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'chat_messages'
               AND COLUMN_NAME = 'user_key'"
        );

        if ($exists > 0) {
            return;
        }

        $tableExists = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_messages'"
        );

        if ($tableExists === 0) {
            return;
        }

        $this->addSql("ALTER TABLE chat_messages ADD user_key VARCHAR(255) NOT NULL DEFAULT 'inconnu'");
        $this->addSql('ALTER TABLE chat_messages MODIFY user_key VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $exists = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'chat_messages'
               AND COLUMN_NAME = 'user_key'"
        );

        if ($exists === 0) {
            return;
        }

        $this->addSql('ALTER TABLE chat_messages DROP COLUMN user_key');
    }
}
