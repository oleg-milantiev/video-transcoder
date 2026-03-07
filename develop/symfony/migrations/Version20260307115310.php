<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260307115310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin user';
    }

    public function up(Schema $schema): void
    {
        // roles must be JSON array: ["ROLE_ADMIN"]
        $this->addSql('INSERT INTO "user" (email, roles, password) VALUES (\'oleg@milantiev.com\', \'["ROLE_ADMIN"]\', \'$2y$13$aMbt0.agYrHEOjmVLRu0tOa94hWeIErYcW6JPUo0EOFX2PoCzus5m\')');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM \"user\" WHERE email = 'oleg@milantiev.com'");
    }
}
