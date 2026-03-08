<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308230500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add meta column to task table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD meta JSONB DEFAULT \'{}\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP meta');
    }
}
