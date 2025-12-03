<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251202100106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, last_activity_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_8A8E26E9A76ED395 (user_id), INDEX conversation_user_last_activity_idx (user_id, last_activity_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation_message (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(16) NOT NULL, content LONGTEXT DEFAULT NULL, source_documents JSON DEFAULT NULL, token_count INT DEFAULT NULL, status VARCHAR(32) NOT NULL, error_message LONGTEXT DEFAULT NULL, streamed_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, conversation_id INT NOT NULL, INDEX IDX_2DEB3E759AC0396 (conversation_id), INDEX conversation_message_conversation_created_at_idx (conversation_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE light_rag_request_log (id INT AUTO_INCREMENT NOT NULL, duration_ms INT NOT NULL, status VARCHAR(32) NOT NULL, error_message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, conversation_id INT NOT NULL, message_id INT DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_E94C0833A76ED395 (user_id), INDEX light_rag_request_log_conversation_idx (conversation_id), INDEX light_rag_request_log_message_idx (message_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_2DEB3E759AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE light_rag_request_log ADD CONSTRAINT FK_E94C08339AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE light_rag_request_log ADD CONSTRAINT FK_E94C0833537A1329 FOREIGN KEY (message_id) REFERENCES conversation_message (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE light_rag_request_log ADD CONSTRAINT FK_E94C0833A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9A76ED395');
        $this->addSql('ALTER TABLE conversation_message DROP FOREIGN KEY FK_2DEB3E759AC0396');
        $this->addSql('ALTER TABLE light_rag_request_log DROP FOREIGN KEY FK_E94C08339AC0396');
        $this->addSql('ALTER TABLE light_rag_request_log DROP FOREIGN KEY FK_E94C0833537A1329');
        $this->addSql('ALTER TABLE light_rag_request_log DROP FOREIGN KEY FK_E94C0833A76ED395');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE conversation_message');
        $this->addSql('DROP TABLE light_rag_request_log');
    }
}
