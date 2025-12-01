<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251128120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add soft delete support for document nodes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_node ADD deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_node DROP deleted_at');
    }
}
