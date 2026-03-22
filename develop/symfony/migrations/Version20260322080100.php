<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322080100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes for log filters in admin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_log_name ON log (name)');
        $this->addSql('CREATE INDEX idx_log_object_id ON log (object_id)');
        $this->addSql('CREATE INDEX idx_log_level ON log (level)');
        $this->addSql('CREATE INDEX idx_log_text ON log (text)');
        $this->addSql('CREATE INDEX idx_log_created_at ON log (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_log_name');
        $this->addSql('DROP INDEX idx_log_object_id');
        $this->addSql('DROP INDEX idx_log_level');
        $this->addSql('DROP INDEX idx_log_text');
        $this->addSql('DROP INDEX idx_log_created_at');
    }
}
