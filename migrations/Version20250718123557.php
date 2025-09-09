<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250718123557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bilan (id INT AUTO_INCREMENT NOT NULL, prescription_id INT NOT NULL, statut VARCHAR(20) NOT NULL, interpretation_automatique LONGTEXT NOT NULL, commentaires_praticien LONGTEXT DEFAULT NULL, recommandations LONGTEXT DEFAULT NULL, scores_detailles JSON NOT NULL COMMENT \'(DC2Type:json)\', score_dg INT DEFAULT NULL, niveau_risque_global VARCHAR(20) DEFAULT NULL, age_developpement_mois INT DEFAULT NULL, profil_graphique JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', points_forts JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', points_vigilance JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', date_generation DATETIME NOT NULL, date_validation DATETIME DEFAULT NULL, chemin_pdf VARCHAR(255) DEFAULT NULL, version INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F4DF4F4493DB413D (prescription_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE item_ide (id INT AUTO_INCREMENT NOT NULL, test_ide_id INT NOT NULL, partie VARCHAR(2) NOT NULL, domaine VARCHAR(5) NOT NULL, ordre INT NOT NULL, texte LONGTEXT NOT NULL, compte_dg TINYINT(1) NOT NULL, age_min_mois INT DEFAULT NULL, age_max_mois INT DEFAULT NULL, instructions LONGTEXT DEFAULT NULL, actif TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_631B69FBF0AA3281 (test_ide_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE prescription (id INT AUTO_INCREMENT NOT NULL, praticien_id INT NOT NULL, patient_id INT NOT NULL, test_id INT NOT NULL, statut VARCHAR(20) NOT NULL, instructions LONGTEXT DEFAULT NULL, commentaires LONGTEXT DEFAULT NULL, date_limite DATE DEFAULT NULL, priorite INT NOT NULL, consentement_rgpd TINYINT(1) NOT NULL, date_consentement DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_1FBFB8D92391866B (praticien_id), INDEX IDX_1FBFB8D96B899279 (patient_id), INDEX IDX_1FBFB8D91E5D0459 (test_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE session (id INT AUTO_INCREMENT NOT NULL, prescription_id INT NOT NULL, statut VARCHAR(20) NOT NULL, reponses JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', scores JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', progression INT NOT NULL, partie_courante VARCHAR(2) DEFAULT NULL, age_chronologique_mois INT NOT NULL, date_naissance DATE NOT NULL, date_debut DATETIME DEFAULT NULL, date_fin DATETIME DEFAULT NULL, duree_minutes INT DEFAULT NULL, observations LONGTEXT DEFAULT NULL, adresse_ip VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_D044D5D493DB413D (prescription_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE test (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, version VARCHAR(10) NOT NULL, age_min_mois INT NOT NULL, age_max_mois INT NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', actif TINYINT(1) NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE test_ide (id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE bilan ADD CONSTRAINT FK_F4DF4F4493DB413D FOREIGN KEY (prescription_id) REFERENCES prescription (id)');
        $this->addSql('ALTER TABLE item_ide ADD CONSTRAINT FK_631B69FBF0AA3281 FOREIGN KEY (test_ide_id) REFERENCES test_ide (id)');
        $this->addSql('ALTER TABLE prescription ADD CONSTRAINT FK_1FBFB8D92391866B FOREIGN KEY (praticien_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE prescription ADD CONSTRAINT FK_1FBFB8D96B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)');
        $this->addSql('ALTER TABLE prescription ADD CONSTRAINT FK_1FBFB8D91E5D0459 FOREIGN KEY (test_id) REFERENCES test (id)');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT FK_D044D5D493DB413D FOREIGN KEY (prescription_id) REFERENCES prescription (id)');
        $this->addSql('ALTER TABLE test_ide ADD CONSTRAINT FK_FD73CE0EBF396750 FOREIGN KEY (id) REFERENCES test (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE patient ADD date_naissance DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bilan DROP FOREIGN KEY FK_F4DF4F4493DB413D');
        $this->addSql('ALTER TABLE item_ide DROP FOREIGN KEY FK_631B69FBF0AA3281');
        $this->addSql('ALTER TABLE prescription DROP FOREIGN KEY FK_1FBFB8D92391866B');
        $this->addSql('ALTER TABLE prescription DROP FOREIGN KEY FK_1FBFB8D96B899279');
        $this->addSql('ALTER TABLE prescription DROP FOREIGN KEY FK_1FBFB8D91E5D0459');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY FK_D044D5D493DB413D');
        $this->addSql('ALTER TABLE test_ide DROP FOREIGN KEY FK_FD73CE0EBF396750');
        $this->addSql('DROP TABLE bilan');
        $this->addSql('DROP TABLE item_ide');
        $this->addSql('DROP TABLE prescription');
        $this->addSql('DROP TABLE session');
        $this->addSql('DROP TABLE test');
        $this->addSql('DROP TABLE test_ide');
        $this->addSql('ALTER TABLE patient DROP date_naissance');
    }
}
