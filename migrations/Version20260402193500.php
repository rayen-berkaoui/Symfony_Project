<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402193500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename post_images.position to sort_position when needed.';
    }

    public function up(Schema $schema): void
    {
        $hasColumn = function (string $table, string $column): bool {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            ) > 0;
        };

        $tableExists = function (string $table): bool {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            ) > 0;
        };

        if (!$tableExists('post_images')) {
            return;
        }

        if ($hasColumn('post_images', 'position') && !$hasColumn('post_images', 'sort_position')) {
            $this->addSql('ALTER TABLE post_images CHANGE `position` sort_position INT NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        $hasColumn = function (string $table, string $column): bool {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            ) > 0;
        };

        if ($hasColumn('post_images', 'sort_position') && !$hasColumn('post_images', 'position')) {
            $this->addSql('ALTER TABLE post_images CHANGE sort_position `position` INT NOT NULL DEFAULT 0');
        }
    }
}
