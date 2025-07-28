<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create leads, lead_data, api_request_logs, and api_response_logs tables';
    }

    public function up(Schema $schema): void
    {
        // Create leads table
        $this->addSql('
            CREATE TABLE leads (
                id INT AUTO_INCREMENT NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                phone VARCHAR(20) DEFAULT NULL,
                date_of_birth DATE DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "active",
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id),
                INDEX idx_lead_email (email),
                INDEX idx_lead_phone (phone),
                INDEX idx_lead_created_at (created_at),
                INDEX idx_lead_status (status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        // Create lead_data table for dynamic fields
        $this->addSql('
            CREATE TABLE lead_data (
                id INT AUTO_INCREMENT NOT NULL,
                lead_id INT NOT NULL,
                field_name VARCHAR(100) NOT NULL,
                field_value TEXT DEFAULT NULL,
                field_type VARCHAR(50) NOT NULL DEFAULT "string",
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id),
                UNIQUE KEY uniq_lead_field (lead_id, field_name),
                INDEX idx_lead_data_lead_field (lead_id, field_name),
                CONSTRAINT FK_lead_data_lead_id FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lead_data');
        $this->addSql('DROP TABLE leads');
    }
}