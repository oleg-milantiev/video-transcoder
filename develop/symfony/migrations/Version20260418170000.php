<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove title from preset table. Create tariff to preset link';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset DROP title');
        $this->addSql('CREATE TABLE tariff_preset (tariff_entity_id UUID NOT NULL, preset_entity_id UUID NOT NULL, PRIMARY KEY (tariff_entity_id, preset_entity_id))');
        $this->addSql('CREATE INDEX IDX_3C8F39A2CE73AC3B ON tariff_preset (tariff_entity_id)');
        $this->addSql('CREATE INDEX IDX_3C8F39A2437EEC20 ON tariff_preset (preset_entity_id)');
        $this->addSql('ALTER TABLE tariff_preset ADD CONSTRAINT FK_3C8F39A2CE73AC3B FOREIGN KEY (tariff_entity_id) REFERENCES tariff (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tariff_preset ADD CONSTRAINT FK_3C8F39A2437EEC20 FOREIGN KEY (preset_entity_id) REFERENCES preset (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE IF EXISTS tariff_preset DROP CONSTRAINT IF EXISTS FK_3C8F39A2CE73AC3B');
        $this->addSql('ALTER TABLE IF EXISTS tariff_preset DROP CONSTRAINT IF EXISTS FK_3C8F39A2437EEC20');
        $this->addSql('DROP INDEX IF EXISTS IDX_3C8F39A2CE73AC3B');
        $this->addSql('DROP INDEX IF EXISTS IDX_3C8F39A2437EEC20');
        $this->addSql('DROP TABLE IF EXISTS tariff_preset');
        $this->addSql("ALTER TABLE preset ADD title VARCHAR(255) NOT NULL DEFAULT ''");
    }
}
