<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251015185201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE password_reset_tokens (token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, is_used TINYINT(1) DEFAULT 0 NOT NULL, id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_3967A2165F37A13B (token), INDEX idx_token (token), INDEX idx_expires_at (expires_at), INDEX idx_user_id (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (email VARCHAR(255) NOT NULL, username VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, last_login_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), INDEX idx_email (email), INDEX idx_username (username), INDEX idx_is_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_3967A216A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_leads_ai_score ON leads');
        $this->addSql('DROP INDEX idx_leads_ai_category ON leads');
        $this->addSql('DROP INDEX idx_leads_ai_scored_at ON leads');
        $this->addSql('ALTER TABLE leads CHANGE ai_score ai_score INT DEFAULT NULL, CHANGE ai_category ai_category VARCHAR(10) DEFAULT NULL, CHANGE ai_reasoning ai_reasoning LONGTEXT DEFAULT NULL, CHANGE ai_suggestions ai_suggestions JSON DEFAULT NULL, CHANGE ai_scored_at ai_scored_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_3967A216A76ED395');
        $this->addSql('DROP TABLE password_reset_tokens');
        $this->addSql('DROP TABLE users');
        $this->addSql('ALTER TABLE leads CHANGE ai_score ai_score INT DEFAULT NULL COMMENT \'AI-generated lead score (0-100)\', CHANGE ai_category ai_category VARCHAR(10) DEFAULT NULL COMMENT \'AI-generated category: hot, warm, or cold\', CHANGE ai_reasoning ai_reasoning TEXT DEFAULT NULL COMMENT \'AI explanation for the score\', CHANGE ai_suggestions ai_suggestions JSON DEFAULT NULL COMMENT \'AI-generated actionable suggestions\', CHANGE ai_scored_at ai_scored_at DATETIME DEFAULT NULL COMMENT \'When AI scoring was performed\'');
        $this->addSql('CREATE INDEX idx_leads_ai_score ON leads (ai_score)');
        $this->addSql('CREATE INDEX idx_leads_ai_category ON leads (ai_category)');
        $this->addSql('CREATE INDEX idx_leads_ai_scored_at ON leads (ai_scored_at)');
    }
}
