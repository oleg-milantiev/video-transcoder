<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index idx_task_video_id on task(video_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_task_video_id ON task (video_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_task_video_id');
    }
}


