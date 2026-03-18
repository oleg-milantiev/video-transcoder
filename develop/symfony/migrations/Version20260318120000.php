<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add started_at column to task';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP started_at');
    }
}

