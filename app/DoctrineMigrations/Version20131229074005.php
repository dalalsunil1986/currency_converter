<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20131229074005 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql", "Migration can only be executed safely on 'mysql'.");
        
        $this->addSql("ALTER TABLE countries ADD CONSTRAINT FK_5D66EBAD38248176 FOREIGN KEY (currency_id) REFERENCES currencies (id)");
        $this->addSql("CREATE UNIQUE INDEX UNIQ_5D66EBAD38248176 ON countries (currency_id)");
        $this->addSql("ALTER TABLE currencies CHANGE symbol symbol VARCHAR(50) NOT NULL");
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql", "Migration can only be executed safely on 'mysql'.");
        
        $this->addSql("ALTER TABLE countries DROP FOREIGN KEY FK_5D66EBAD38248176");
        $this->addSql("DROP INDEX UNIQ_5D66EBAD38248176 ON countries");
        $this->addSql("ALTER TABLE currencies CHANGE symbol symbol VARCHAR(50) DEFAULT NULL");
    }
}
