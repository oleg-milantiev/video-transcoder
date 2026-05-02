<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add loading boolean column to video table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video ADD COLUMN loading BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video DROP COLUMN loading');
    }
}
