<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250909121023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE patient_activation_tokens (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ip_created VARCHAR(45) DEFAULT NULL, user_agent_created LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_2A7E1EC9B3BC57DA (token_hash), INDEX IDX_2A7E1EC96B899279 (patient_id), INDEX idx_token_hash (token_hash), INDEX idx_expires_at (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE patient_activation_tokens ADD CONSTRAINT FK_2A7E1EC96B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE patient DROP plain_password');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE patient_activation_tokens DROP FOREIGN KEY FK_2A7E1EC96B899279');
        $this->addSql('DROP TABLE patient_activation_tokens');
        $this->addSql('ALTER TABLE patient ADD plain_password VARCHAR(255) DEFAULT NULL');
    }
}
