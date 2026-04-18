<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418153201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move width, height and bitrate from preset to task.meta';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE preset DROP width');
        $this->addSql('ALTER TABLE preset DROP height');
        $this->addSql('ALTER TABLE preset DROP bitrate');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE preset ADD width INT NOT NULL');
        $this->addSql('ALTER TABLE preset ADD height INT NOT NULL');
        $this->addSql('ALTER TABLE preset ADD bitrate DOUBLE PRECISION NOT NULL');
    }
}
