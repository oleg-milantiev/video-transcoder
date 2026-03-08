<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308123500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add meta column to video table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video ADD meta JSONB NOT NULL DEFAULT \'{}\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video DROP meta');
    }
}
