<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509080644 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `admin` (poste VARCHAR(100) DEFAULT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE avis (id INT AUTO_INCREMENT NOT NULL, note SMALLINT NOT NULL, commentaire LONGTEXT NOT NULL, date_avis DATETIME NOT NULL, statut VARCHAR(255) NOT NULL, client_id INT NOT NULL, hotel_id INT DEFAULT NULL, chauffeur_id INT DEFAULT NULL, INDEX IDX_8F91ABF019EB6921 (client_id), INDEX IDX_8F91ABF03243BB18 (hotel_id), INDEX IDX_8F91ABF085C0B3BE (chauffeur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chauffeur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, telephone VARCHAR(20) NOT NULL, voiture VARCHAR(150) NOT NULL, photo VARCHAR(255) DEFAULT NULL, prix_transfert NUMERIC(10, 2) NOT NULL, actif TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client (adresse VARCHAR(255) DEFAULT NULL, date_naissance DATE DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE destination (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, pays VARCHAR(100) NOT NULL, description LONGTEXT NOT NULL, photo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE disponibilite_chauffeur (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, type_trajet VARCHAR(255) NOT NULL, est_disponible TINYINT NOT NULL, chauffeur_id INT NOT NULL, INDEX IDX_9043CFA585C0B3BE (chauffeur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hotel (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(200) NOT NULL, adresse VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, prix_par_nuit NUMERIC(10, 2) NOT NULL, etoiles SMALLINT NOT NULL, photo_principale VARCHAR(255) DEFAULT NULL, actif TINYINT NOT NULL, created_at DATETIME NOT NULL, destination_id INT NOT NULL, INDEX IDX_3535ED9816C6140 (destination_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE paiement (id INT AUTO_INCREMENT NOT NULL, montant NUMERIC(10, 2) NOT NULL, date_paiement DATETIME DEFAULT NULL, methode VARCHAR(255) NOT NULL, statut VARCHAR(255) NOT NULL, transaction_id VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, reservation_id INT NOT NULL, UNIQUE INDEX UNIQ_B1DC7A1EB83297E7 (reservation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE photo_hotel (id INT AUTO_INCREMENT NOT NULL, chemin VARCHAR(255) NOT NULL, legende VARCHAR(200) DEFAULT NULL, hotel_id INT NOT NULL, INDEX IDX_B902596E3243BB18 (hotel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, statut VARCHAR(255) NOT NULL, nombre_personnes INT NOT NULL, nombre_nuits INT DEFAULT NULL, prix_total NUMERIC(10, 2) NOT NULL, type_transfert VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, reference VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, client_id INT NOT NULL, voyage_id INT NOT NULL, hotel_id INT DEFAULT NULL, chauffeur_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_42C84955AEA34913 (reference), INDEX IDX_42C8495519EB6921 (client_id), INDEX IDX_42C8495568C9E5AF (voyage_id), INDEX IDX_42C849553243BB18 (hotel_id), INDEX IDX_42C8495585C0B3BE (chauffeur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, roles JSON NOT NULL, created_at DATETIME NOT NULL, type VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE voyage (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, date_depart DATE NOT NULL, date_retour DATE NOT NULL, prix NUMERIC(10, 2) NOT NULL, places_disponibles INT NOT NULL, photo VARCHAR(255) DEFAULT NULL, actif TINYINT NOT NULL, created_at DATETIME NOT NULL, destination_id INT NOT NULL, INDEX IDX_3F9D8955816C6140 (destination_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `admin` ADD CONSTRAINT FK_880E0D76BF396750 FOREIGN KEY (id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF019EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF03243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id)');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF085C0B3BE FOREIGN KEY (chauffeur_id) REFERENCES chauffeur (id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455BF396750 FOREIGN KEY (id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE disponibilite_chauffeur ADD CONSTRAINT FK_9043CFA585C0B3BE FOREIGN KEY (chauffeur_id) REFERENCES chauffeur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hotel ADD CONSTRAINT FK_3535ED9816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1EB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE photo_hotel ADD CONSTRAINT FK_B902596E3243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495519EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495568C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849553243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495585C0B3BE FOREIGN KEY (chauffeur_id) REFERENCES chauffeur (id)');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D8955816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `admin` DROP FOREIGN KEY FK_880E0D76BF396750');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF019EB6921');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF03243BB18');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF085C0B3BE');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455BF396750');
        $this->addSql('ALTER TABLE disponibilite_chauffeur DROP FOREIGN KEY FK_9043CFA585C0B3BE');
        $this->addSql('ALTER TABLE hotel DROP FOREIGN KEY FK_3535ED9816C6140');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1EB83297E7');
        $this->addSql('ALTER TABLE photo_hotel DROP FOREIGN KEY FK_B902596E3243BB18');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495519EB6921');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495568C9E5AF');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849553243BB18');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495585C0B3BE');
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D8955816C6140');
        $this->addSql('DROP TABLE `admin`');
        $this->addSql('DROP TABLE avis');
        $this->addSql('DROP TABLE chauffeur');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE destination');
        $this->addSql('DROP TABLE disponibilite_chauffeur');
        $this->addSql('DROP TABLE hotel');
        $this->addSql('DROP TABLE paiement');
        $this->addSql('DROP TABLE photo_hotel');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE voyage');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
