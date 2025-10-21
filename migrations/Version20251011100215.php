<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251011100215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE customers (
              email VARCHAR(255) NOT NULL,
              phone VARCHAR(20) NOT NULL,
              first_name VARCHAR(100) DEFAULT NULL,
              last_name VARCHAR(100) DEFAULT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              INDEX idx_email (email),
              INDEX idx_phone (phone),
              UNIQUE INDEX unique_email_phone (email, phone),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE events (
              event_type VARCHAR(50) NOT NULL,
              entity_type VARCHAR(50) DEFAULT NULL,
              entity_id INT DEFAULT NULL,
              user_id INT DEFAULT NULL,
              details JSON DEFAULT NULL,
              retry_count INT DEFAULT 0 NOT NULL,
              error_message LONGTEXT DEFAULT NULL,
              ip_address VARCHAR(45) DEFAULT NULL,
              user_agent LONGTEXT DEFAULT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              id BIGINT AUTO_INCREMENT NOT NULL,
              INDEX idx_event_type_created (event_type, created_at),
              INDEX idx_entity (entity_type, entity_id),
              INDEX idx_user_id (user_id),
              INDEX idx_created_at (created_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE failed_deliveries (
              cdp_system_name VARCHAR(50) NOT NULL,
              error_code VARCHAR(50) DEFAULT NULL,
              error_message LONGTEXT DEFAULT NULL,
              retry_count INT DEFAULT 0 NOT NULL,
              max_retries INT DEFAULT 3 NOT NULL,
              next_retry_at DATETIME DEFAULT NULL,
              status VARCHAR(20) DEFAULT 'pending' NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              resolved_at DATETIME DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              lead_id INT NOT NULL,
              INDEX idx_lead_id (lead_id),
              INDEX idx_status_next_retry (status, next_retry_at),
              INDEX idx_created_at (created_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE lead_properties (
              property_id VARCHAR(100) DEFAULT NULL,
              development_id VARCHAR(100) DEFAULT NULL,
              partner_id VARCHAR(100) DEFAULT NULL,
              property_type VARCHAR(50) DEFAULT NULL,
              price NUMERIC(15, 2) DEFAULT NULL,
              location VARCHAR(255) DEFAULT NULL,
              city VARCHAR(100) DEFAULT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              lead_id INT NOT NULL,
              UNIQUE INDEX UNIQ_AF582A5B55458D (lead_id),
              INDEX idx_lead_id (lead_id),
              INDEX idx_property_id (property_id),
              INDEX idx_development_id (development_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE leads (
              lead_uuid VARCHAR(36) NOT NULL,
              application_name VARCHAR(50) NOT NULL,
              status VARCHAR(20) DEFAULT 'new' NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              customer_id INT NOT NULL,
              UNIQUE INDEX UNIQ_179045523089157C (lead_uuid),
              INDEX IDX_179045529395C3F3 (customer_id),
              INDEX idx_customer_created (customer_id, created_at),
              INDEX idx_application_created (application_name, created_at),
              INDEX idx_status_created (status, created_at),
              INDEX idx_lead_uuid (lead_uuid),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              failed_deliveries
            ADD
              CONSTRAINT FK_4730BBE355458D FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              lead_properties
            ADD
              CONSTRAINT FK_AF582A5B55458D FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              leads
            ADD
              CONSTRAINT FK_179045529395C3F3 FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE failed_deliveries DROP FOREIGN KEY FK_4730BBE355458D');
        $this->addSql('ALTER TABLE lead_properties DROP FOREIGN KEY FK_AF582A5B55458D');
        $this->addSql('ALTER TABLE leads DROP FOREIGN KEY FK_179045529395C3F3');
        $this->addSql('DROP TABLE customers');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE failed_deliveries');
        $this->addSql('DROP TABLE lead_properties');
        $this->addSql('DROP TABLE leads');
    }
}
