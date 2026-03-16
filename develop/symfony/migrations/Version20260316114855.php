<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316114855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add log column to tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset ADD log JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD log JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD log JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE video ADD log JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset DROP log');
        $this->addSql('ALTER TABLE task DROP log');
        $this->addSql('ALTER TABLE "user" DROP log');
        $this->addSql('ALTER TABLE video DROP log');
    }
}
