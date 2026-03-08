<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tariff table and user.tariff_id relation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tariff (id SERIAL NOT NULL, title VARCHAR(255) NOT NULL, time_delay INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE "user" ADD tariff_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649357C0A59 FOREIGN KEY (tariff_id) REFERENCES tariff (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8D93D649357C0A59 ON "user" (tariff_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649357C0A59');
        $this->addSql('DROP INDEX IDX_8D93D649357C0A59');
        $this->addSql('ALTER TABLE "user" DROP tariff_id');
        $this->addSql('DROP TABLE tariff');
    }
}
