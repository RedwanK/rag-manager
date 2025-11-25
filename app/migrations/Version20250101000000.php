<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create repository_config, document_node, and sync_log tables for GitHub sync.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE repository_config (id INT AUTO_INCREMENT NOT NULL, owner VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, default_branch VARCHAR(255) DEFAULT NULL, token VARCHAR(2048) NOT NULL, last_sync_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", last_sync_status VARCHAR(64) DEFAULT NULL, last_sync_message LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE document_node (id INT AUTO_INCREMENT NOT NULL, repository_config_id INT NOT NULL, path VARCHAR(2048) NOT NULL, type VARCHAR(32) NOT NULL, size BIGINT DEFAULT NULL, last_modified DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", last_synced_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", last_sync_status VARCHAR(64) DEFAULT NULL, INDEX IDX_789BDFEA2A61E73C (repository_config_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sync_log (id INT AUTO_INCREMENT NOT NULL, repository_config_id INT NOT NULL, started_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", finished_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", status VARCHAR(64) NOT NULL, message LONGTEXT DEFAULT NULL, triggered_by VARCHAR(255) DEFAULT NULL, INDEX IDX_13DD75EC2A61E73C (repository_config_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document_node ADD CONSTRAINT FK_789BDFEA2A61E73C FOREIGN KEY (repository_config_id) REFERENCES repository_config (id)');
        $this->addSql('ALTER TABLE sync_log ADD CONSTRAINT FK_13DD75EC2A61E73C FOREIGN KEY (repository_config_id) REFERENCES repository_config (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_node DROP FOREIGN KEY FK_789BDFEA2A61E73C');
        $this->addSql('ALTER TABLE sync_log DROP FOREIGN KEY FK_13DD75EC2A61E73C');
        $this->addSql('DROP TABLE document_node');
        $this->addSql('DROP TABLE repository_config');
        $this->addSql('DROP TABLE sync_log');
    }
}
