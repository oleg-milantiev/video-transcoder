<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317135728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preset bitrate change type to float';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset ALTER bitrate TYPE DOUBLE PRECISION');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset ALTER bitrate TYPE INT');
    }
}
