<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Legacy: rename chat_messages (username→user_key, message→content), chat_bans (username→user_key) if applicable.';
    }

    public function up(Schema $schema): void
    {
        $has = function (string $table, string $column): bool {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            ) > 0;
        };

        if ($has('chat_messages', 'username') && !$has('chat_messages', 'user_key')) {
            $this->addSql('ALTER TABLE chat_messages CHANGE username user_key VARCHAR(255) NOT NULL');
        }
        if ($has('chat_messages', 'message') && !$has('chat_messages', 'content')) {
            $this->addSql('ALTER TABLE chat_messages CHANGE message content LONGTEXT NOT NULL');
        }

        $indexExists = function (string $table, string $indexName): bool {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$table, $indexName]
            ) > 0;
        };
        if ($indexExists('chat_bans', 'unique_username')) {
            $this->addSql('ALTER TABLE chat_bans DROP INDEX unique_username');
        }
        if ($indexExists('chat_bans', 'idx_banned_until')) {
            $this->addSql('ALTER TABLE chat_bans DROP INDEX idx_banned_until');
        }

        if ($has('chat_bans', 'username') && !$has('chat_bans', 'user_key')) {
            $this->addSql('ALTER TABLE chat_bans CHANGE username user_key VARCHAR(255) NOT NULL');
        }
        if ($has('chat_bans', 'banned_until') && !$has('chat_bans', 'expires_at')) {
            $this->addSql('ALTER TABLE chat_bans CHANGE banned_until expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        } elseif (!$has('chat_bans', 'expires_at') && !$has('chat_bans', 'banned_until')) {
            $this->addSql('ALTER TABLE chat_bans ADD expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }

        if ($has('chat_bans', 'banned_at') && !$has('chat_bans', 'created_at')) {
            $this->addSql('ALTER TABLE chat_bans CHANGE banned_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        } elseif (!$has('chat_bans', 'created_at') && !$has('chat_bans', 'banned_at')) {
            $this->addSql('ALTER TABLE chat_bans ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\' DEFAULT CURRENT_TIMESTAMP');
        }

        if ($has('chat_bans', 'banned_by')) {
            $this->addSql('ALTER TABLE chat_bans DROP COLUMN banned_by');
        }

        if ($has('chat_bans', 'user_key') && !$indexExists('chat_bans', 'uniq_chat_bans_user_key')) {
            $this->addSql('CREATE UNIQUE INDEX uniq_chat_bans_user_key ON chat_bans (user_key)');
        }
    }

    public function down(Schema $schema): void
    {
    }
}
