<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'activities: unique (post_id, user_key, activity_type, comment_id) pour réactions post vs commentaire séparées.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activities DROP INDEX unique_activity');
        $this->addSql('ALTER TABLE activities ADD UNIQUE INDEX unique_activity (post_id, user_key, activity_type, comment_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activities DROP INDEX unique_activity');
        $this->addSql('ALTER TABLE activities ADD UNIQUE INDEX unique_activity (post_id, user_key, activity_type)');
    }
}
