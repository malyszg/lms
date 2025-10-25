<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025121804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create customer_preferences table';
    }

    public function up(Schema $schema): void
    {
        // Create customer_preferences table
        $this->addSql('CREATE TABLE customer_preferences (
            id INT AUTO_INCREMENT NOT NULL,
            customer_id INT NOT NULL,
            price_min DECIMAL(10,2) DEFAULT NULL,
            price_max DECIMAL(10,2) DEFAULT NULL,
            location VARCHAR(255) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE INDEX unique_customer_preferences (customer_id),
            CONSTRAINT customer_preferences_ibfk_1 FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // Drop customer_preferences table
        $this->addSql('DROP TABLE customer_preferences');
    }
}