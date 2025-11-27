<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251127103229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ingestion_log (id INT AUTO_INCREMENT NOT NULL, level VARCHAR(255) NOT NULL, message VARCHAR(255) NOT NULL, context JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, ingestion_queue_item_id INT NOT NULL, INDEX IDX_CAFAD661F48D6B49 (ingestion_queue_item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ingestion_queue_item (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, source VARCHAR(255) NOT NULL, started_at DATETIME DEFAULT NULL, ended_at DATETIME DEFAULT NULL, rag_message VARCHAR(255) DEFAULT NULL, storage_path VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, document_node_id INT NOT NULL, added_by_id INT NOT NULL, UNIQUE INDEX UNIQ_A4502734E262390B (document_node_id), INDEX IDX_A450273455B127A4 (added_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ingestion_log ADD CONSTRAINT FK_CAFAD661F48D6B49 FOREIGN KEY (ingestion_queue_item_id) REFERENCES ingestion_queue_item (id)');
        $this->addSql('ALTER TABLE ingestion_queue_item ADD CONSTRAINT FK_A4502734E262390B FOREIGN KEY (document_node_id) REFERENCES document_node (id)');
        $this->addSql('ALTER TABLE ingestion_queue_item ADD CONSTRAINT FK_A450273455B127A4 FOREIGN KEY (added_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE document_node ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE repository_config ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE sync_log ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ingestion_log DROP FOREIGN KEY FK_CAFAD661F48D6B49');
        $this->addSql('ALTER TABLE ingestion_queue_item DROP FOREIGN KEY FK_A4502734E262390B');
        $this->addSql('ALTER TABLE ingestion_queue_item DROP FOREIGN KEY FK_A450273455B127A4');
        $this->addSql('DROP TABLE ingestion_log');
        $this->addSql('DROP TABLE ingestion_queue_item');
        $this->addSql('ALTER TABLE document_node DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE repository_config DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE sync_log DROP created_at, DROP updated_at');
    }
}
