<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251202100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add chat conversations, messages and LightRag request log tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE conversation (id INT NOT NULL, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX conversation_user_last_activity_idx ON conversation (user_id, last_activity_at)');
        $this->addSql('CREATE TABLE conversation_message (id INT NOT NULL, conversation_id INT NOT NULL, role VARCHAR(16) NOT NULL, content TEXT DEFAULT NULL, source_documents JSON DEFAULT NULL, token_count INT DEFAULT NULL, status VARCHAR(32) NOT NULL, error_message TEXT DEFAULT NULL, streamed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX conversation_message_conversation_created_at_idx ON conversation_message (conversation_id, created_at)');
        $this->addSql('CREATE TABLE light_rag_request_log (id INT NOT NULL, conversation_id INT NOT NULL, message_id INT DEFAULT NULL, user_id INT NOT NULL, duration_ms INT NOT NULL, status VARCHAR(32) NOT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX light_rag_request_log_conversation_idx ON light_rag_request_log (conversation_id)');
        $this->addSql('CREATE INDEX light_rag_request_log_message_idx ON light_rag_request_log (message_id)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E1A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_1A9BE8489AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE light_rag_request_log ADD CONSTRAINT FK_C0E10E20A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE light_rag_request_log ADD CONSTRAINT FK_C0E10E209AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE light_rag_request_log ADD CONSTRAINT FK_C0E10E20537A1329 FOREIGN KEY (message_id) REFERENCES conversation_message (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE light_rag_request_log DROP CONSTRAINT FK_C0E10E209AC0396');
        $this->addSql('ALTER TABLE light_rag_request_log DROP CONSTRAINT FK_C0E10E20537A1329');
        $this->addSql('ALTER TABLE light_rag_request_log DROP CONSTRAINT FK_C0E10E20A76ED395');
        $this->addSql('ALTER TABLE conversation_message DROP CONSTRAINT FK_1A9BE8489AC0396');
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT FK_8A8E26E1A76ED395');
        $this->addSql('DROP TABLE light_rag_request_log');
        $this->addSql('DROP TABLE conversation_message');
        $this->addSql('DROP TABLE conversation');
    }
}
