<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329092408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new tariff fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tariff ADD video_duration INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE tariff ADD video_size DOUBLE PRECISION NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE tariff ADD max_width INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE tariff ADD max_height INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE tariff ADD storage_gb DOUBLE PRECISION NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE tariff ADD storage_hour INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tariff DROP video_duration');
        $this->addSql('ALTER TABLE tariff DROP video_size');
        $this->addSql('ALTER TABLE tariff DROP max_width');
        $this->addSql('ALTER TABLE tariff DROP max_height');
        $this->addSql('ALTER TABLE tariff DROP storage_gb');
        $this->addSql('ALTER TABLE tariff DROP storage_hour');
    }
}
