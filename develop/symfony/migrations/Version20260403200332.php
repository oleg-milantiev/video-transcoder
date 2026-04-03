<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403200332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add log.action field';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('TRUNCATE log');
        $this->addSql('ALTER TABLE log ADD action VARCHAR(100) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE log DROP action');
    }
}
