<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add author_key to posts to identify the owner of a publication for notifications.';
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

        if (!$has('posts', 'author_key')) {
            $this->addSql('ALTER TABLE posts ADD author_key VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $has = function (string $table, string $column): bool {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            ) > 0;
        };

        if ($has('posts', 'author_key')) {
            $this->addSql('ALTER TABLE posts DROP COLUMN author_key');
        }
    }
}
