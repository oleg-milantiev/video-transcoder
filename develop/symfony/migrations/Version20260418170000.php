<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove title from preset table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset DROP title');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE preset ADD title VARCHAR(255) NOT NULL DEFAULT ''");
    }
}
