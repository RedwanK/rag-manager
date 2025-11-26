<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add status column to user entity to support account lifecycle management.
 */
final class Version20251128000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to user entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD status VARCHAR(32) DEFAULT 'active' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP status');
    }
}
