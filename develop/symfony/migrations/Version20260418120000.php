<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename preset.codec to video_codec, add audio_codec and format columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset RENAME COLUMN codec TO video_codec');
        $this->addSql("ALTER TABLE preset ADD COLUMN audio_codec VARCHAR(50) NOT NULL DEFAULT 'aac'");
        $this->addSql("ALTER TABLE preset ADD COLUMN format VARCHAR(10) NOT NULL DEFAULT 'mp4'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preset DROP COLUMN audio_codec');
        $this->addSql('ALTER TABLE preset DROP COLUMN format');
        $this->addSql('ALTER TABLE preset RENAME COLUMN video_codec TO codec');
    }
}
