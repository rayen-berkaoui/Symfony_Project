<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scope flags to chat_bans (chat/posts/comments/reactions/shares).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_bans ADD block_chat TINYINT(1) NOT NULL DEFAULT 1, ADD block_posts TINYINT(1) NOT NULL DEFAULT 1, ADD block_comments TINYINT(1) NOT NULL DEFAULT 1, ADD block_reactions TINYINT(1) NOT NULL DEFAULT 1, ADD block_shares TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_bans DROP block_chat, DROP block_posts, DROP block_comments, DROP block_reactions, DROP block_shares');
    }
}
