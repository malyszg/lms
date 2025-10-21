<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251016074953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove password_reset_tokens table - password reset functionality removed';
    }

    public function up(Schema $schema): void
    {
        // Remove password reset tokens table
        $this->addSql('DROP TABLE IF EXISTS password_reset_tokens');
    }

    public function down(Schema $schema): void
    {
        // Restore password reset tokens table if needed for rollback
        $this->addSql('
            CREATE TABLE password_reset_tokens (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                is_used TINYINT(1) DEFAULT 0 NOT NULL,
                UNIQUE INDEX UNIQ_238F5F355F37A13B (token),
                INDEX idx_token (token),
                INDEX idx_expires_at (expires_at),
                INDEX idx_user_id (user_id),
                INDEX IDX_238F5F35A76ED395 (user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');
        
        $this->addSql('
            ALTER TABLE password_reset_tokens 
            ADD CONSTRAINT FK_238F5F35A76ED395 
            FOREIGN KEY (user_id) REFERENCES users (id) 
            ON DELETE CASCADE
        ');
    }
}