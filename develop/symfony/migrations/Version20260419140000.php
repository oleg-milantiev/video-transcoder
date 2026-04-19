<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bitrate JSONB field to preset table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset ADD bitrate JSONB DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN preset.bitrate IS \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset DROP bitrate');
    }
}
