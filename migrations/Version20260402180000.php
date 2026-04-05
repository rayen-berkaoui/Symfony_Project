<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\PostTagIndexBuilder;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add posts.hashtags_index and backfill from content.';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['posts'])) {
            return;
        }

        if ($this->postsHasHashtagsIndexColumn($sm)) {
            return;
        }

        $p = $this->connection->getDatabasePlatform();
        if ($p instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE posts ADD COLUMN hashtags_index CLOB DEFAULT NULL');
        } elseif ($p instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE posts ADD hashtags_index LONGTEXT DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE posts ADD hashtags_index TEXT DEFAULT NULL');
        }
    }

    public function postUp(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['posts'])) {
            return;
        }

        if (!$this->postsHasHashtagsIndexColumn($sm)) {
            return;
        }

        $builder = new PostTagIndexBuilder();
        $rows = $this->connection->fetchAllAssociative('SELECT id, content FROM posts');
        foreach ($rows as $row) {
            $idx = $builder->build((string) ($row['content'] ?? ''));
            $this->connection->executeStatement(
                'UPDATE posts SET hashtags_index = ? WHERE id = ?',
                ['' === $idx ? null : $idx, $row['id']]
            );
        }
    }

    private function postsHasHashtagsIndexColumn(object $sm): bool
    {
        foreach ($sm->listTableColumns('posts') as $column) {
            if (strtolower($column->getName()) === 'hashtags_index') {
                return true;
            }
        }

        return false;
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['posts']) || !$this->postsHasHashtagsIndexColumn($sm)) {
            return;
        }

        $this->addSql('ALTER TABLE posts DROP hashtags_index');
    }
}
