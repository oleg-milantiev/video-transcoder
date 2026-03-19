<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260319110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename preset.name to preset.title';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset RENAME COLUMN name TO title');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset RENAME COLUMN title TO name');
    }
}

