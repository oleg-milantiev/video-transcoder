<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE payment (
                id            UUID                        NOT NULL,
                user_id       UUID                        NOT NULL,
                status        VARCHAR(20)                 NOT NULL,
                currency      VARCHAR(3)                  NOT NULL,
                gateway       VARCHAR(20)                 NOT NULL,
                external_id   VARCHAR(255)                DEFAULT NULL,
                payment_method VARCHAR(100)               DEFAULT NULL,
                meta          JSONB                       NOT NULL DEFAULT '{}',
                plan_snapshot VARCHAR(255)                NOT NULL,
                invoice_url   VARCHAR(2048)               DEFAULT NULL,
                amount        INTEGER                     NOT NULL,
                created_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                paid_at       TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                valid_until   TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_payment_user_id ON payment (user_id)');
        $this->addSql('CREATE INDEX idx_payment_user_status ON payment (user_id, status)');
        $this->addSql('CREATE INDEX idx_payment_gateway_external ON payment (gateway, external_id)');

        $this->addSql(<<<'SQL'
            ALTER TABLE payment
                ADD CONSTRAINT fk_payment_user_id
                FOREIGN KEY (user_id)
                REFERENCES "user" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT fk_payment_user_id');
        $this->addSql('DROP TABLE payment');
    }
}

