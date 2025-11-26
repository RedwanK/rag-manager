<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251126091124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_node (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(2048) NOT NULL, type VARCHAR(32) NOT NULL, size BIGINT DEFAULT NULL, last_modified DATETIME DEFAULT NULL, last_synced_at DATETIME DEFAULT NULL, last_sync_status VARCHAR(64) DEFAULT NULL, repository_config_id INT NOT NULL, INDEX IDX_22CB64D6FCA18B8B (repository_config_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE repository_config (id INT AUTO_INCREMENT NOT NULL, owner VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, default_branch VARCHAR(255) DEFAULT NULL, token VARCHAR(2048) NOT NULL, last_sync_at DATETIME DEFAULT NULL, last_sync_status VARCHAR(64) DEFAULT NULL, last_sync_message LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sync_log (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, status VARCHAR(64) NOT NULL, message LONGTEXT DEFAULT NULL, triggered_by VARCHAR(255) DEFAULT NULL, repository_config_id INT NOT NULL, INDEX IDX_31711176FCA18B8B (repository_config_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document_node ADD CONSTRAINT FK_22CB64D6FCA18B8B FOREIGN KEY (repository_config_id) REFERENCES repository_config (id)');
        $this->addSql('ALTER TABLE sync_log ADD CONSTRAINT FK_31711176FCA18B8B FOREIGN KEY (repository_config_id) REFERENCES repository_config (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document_node DROP FOREIGN KEY FK_22CB64D6FCA18B8B');
        $this->addSql('ALTER TABLE sync_log DROP FOREIGN KEY FK_31711176FCA18B8B');
        $this->addSql('DROP TABLE document_node');
        $this->addSql('DROP TABLE repository_config');
        $this->addSql('DROP TABLE sync_log');
    }
}
