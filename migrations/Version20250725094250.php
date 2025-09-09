<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250725094250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE passation (id INT AUTO_INCREMENT NOT NULL, prescription_id INT NOT NULL, statut VARCHAR(20) NOT NULL, reponses JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', scores JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', progression INT NOT NULL, partie_courante VARCHAR(2) DEFAULT NULL, age_chronologique_mois INT NOT NULL, date_naissance DATE NOT NULL, date_debut DATETIME DEFAULT NULL, date_fin DATETIME DEFAULT NULL, duree_minutes INT DEFAULT NULL, observations LONGTEXT DEFAULT NULL, adresse_ip VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_15BB602993DB413D (prescription_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE passation ADD CONSTRAINT FK_15BB602993DB413D FOREIGN KEY (prescription_id) REFERENCES prescription (id)');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY FK_D044D5D493DB413D');
        $this->addSql('DROP TABLE session');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE session (id INT AUTO_INCREMENT NOT NULL, prescription_id INT NOT NULL, statut VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, reponses JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', scores JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', progression INT NOT NULL, partie_courante VARCHAR(2) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, age_chronologique_mois INT NOT NULL, date_naissance DATE NOT NULL, date_debut DATETIME DEFAULT NULL, date_fin DATETIME DEFAULT NULL, duree_minutes INT DEFAULT NULL, observations LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, adresse_ip VARCHAR(45) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, user_agent LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_D044D5D493DB413D (prescription_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT FK_D044D5D493DB413D FOREIGN KEY (prescription_id) REFERENCES prescription (id)');
        $this->addSql('ALTER TABLE passation DROP FOREIGN KEY FK_15BB602993DB413D');
        $this->addSql('DROP TABLE passation');
    }
}
