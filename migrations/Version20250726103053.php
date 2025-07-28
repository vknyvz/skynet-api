<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726103053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), INDEX idx_user_username (username), INDEX idx_user_email (email), INDEX idx_user_active (is_active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('DROP INDEX idx_lead_data_lead_field ON lead_data');
        $this->addSql('ALTER TABLE lead_data CHANGE field_value field_value LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE leads RENAME INDEX email TO UNIQ_17904552E7927C74');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE users');
        $this->addSql('ALTER TABLE lead_data CHANGE field_value field_value TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_lead_data_lead_field ON lead_data (lead_id, field_name)');
        $this->addSql('ALTER TABLE leads RENAME INDEX uniq_17904552e7927c74 TO email');
    }
}
