<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on task.user_id for getStorageSize quota check';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_task_user_storage ON task (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_task_user_storage');
    }
}
