<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add post_images, migrate posts.image_path, drop image_path column.';
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
            $this->addSql('CREATE TABLE post_images (
                id INT AUTO_INCREMENT NOT NULL,
                post_id INT NOT NULL,
                path LONGTEXT NOT NULL,
                sort_position INT NOT NULL DEFAULT 0,
                PRIMARY KEY(id),
                INDEX idx_post_images_post_id (post_id),
                CONSTRAINT FK_post_images_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if ($hasColumn('posts', 'image_path')) {
            $this->addSql('INSERT INTO post_images (post_id, path, sort_position)
                SELECT id, image_path, 0 FROM posts
                WHERE image_path IS NOT NULL AND TRIM(image_path) <> \'\'');
            $this->addSql('ALTER TABLE posts DROP COLUMN image_path');
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

        if (!$hasColumn('posts', 'image_path')) {
            $this->addSql('ALTER TABLE posts ADD image_path LONGTEXT DEFAULT NULL');
        }
        $this->addSql('UPDATE posts p
            INNER JOIN (
                SELECT post_id, MIN(id) AS min_id FROM post_images GROUP BY post_id
            ) x ON p.id = x.post_id
            INNER JOIN post_images pi ON pi.id = x.min_id
            SET p.image_path = pi.path');
        $this->addSql('DROP TABLE IF EXISTS post_images');
    }
}
