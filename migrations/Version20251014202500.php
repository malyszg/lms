<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add AI scoring cache columns to leads table
 */
final class Version20251014202500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AI scoring cache columns to leads table for performance optimization';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leads ADD COLUMN ai_score INT NULL COMMENT "AI-generated lead score (0-100)"');
        $this->addSql('ALTER TABLE leads ADD COLUMN ai_category VARCHAR(10) NULL COMMENT "AI-generated category: hot, warm, or cold"');
        $this->addSql('ALTER TABLE leads ADD COLUMN ai_reasoning TEXT NULL COMMENT "AI explanation for the score"');
        $this->addSql('ALTER TABLE leads ADD COLUMN ai_suggestions JSON NULL COMMENT "AI-generated actionable suggestions"');
        $this->addSql('ALTER TABLE leads ADD COLUMN ai_scored_at DATETIME NULL COMMENT "When AI scoring was performed"');
        
        $this->addSql('CREATE INDEX idx_leads_ai_score ON leads(ai_score)');
        $this->addSql('CREATE INDEX idx_leads_ai_category ON leads(ai_category)');
        $this->addSql('CREATE INDEX idx_leads_ai_scored_at ON leads(ai_scored_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_leads_ai_scored_at ON leads');
        $this->addSql('DROP INDEX idx_leads_ai_category ON leads');
        $this->addSql('DROP INDEX idx_leads_ai_score ON leads');
        
        $this->addSql('ALTER TABLE leads DROP COLUMN ai_scored_at');
        $this->addSql('ALTER TABLE leads DROP COLUMN ai_suggestions');
        $this->addSql('ALTER TABLE leads DROP COLUMN ai_reasoning');
        $this->addSql('ALTER TABLE leads DROP COLUMN ai_category');
        $this->addSql('ALTER TABLE leads DROP COLUMN ai_score');
    }
}

