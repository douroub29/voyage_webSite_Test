<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Voyage : compagnie aérienne, numéro de vol, capacité avion (nombre_places) et places disponibles gérées par le système';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voyage ADD compagnie_aerienne VARCHAR(30) DEFAULT NULL, ADD numero_vol VARCHAR(10) DEFAULT NULL, ADD nombre_places INT DEFAULT NULL');

        // Les voyages existants : compagnie par défaut Tunisair (à corriger dans l'admin si besoin)
        $this->addSql("UPDATE voyage SET compagnie_aerienne = 'tunisair' WHERE compagnie_aerienne IS NULL");

        // Avant : places_disponibles = capacité totale. Elle devient la capacité de l'avion…
        $this->addSql('UPDATE voyage SET nombre_places = places_disponibles');

        // …et places_disponibles = capacité - places déjà réservées (hors annulées)
        $this->addSql("UPDATE voyage v SET v.places_disponibles = GREATEST(0, v.nombre_places - COALESCE(
            (SELECT SUM(r.nombre_personnes) FROM reservation r WHERE r.voyage_id = v.id AND r.statut <> 'annulee'), 0))");

        $this->addSql('ALTER TABLE voyage MODIFY compagnie_aerienne VARCHAR(30) NOT NULL, MODIFY nombre_places INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE voyage SET places_disponibles = nombre_places');
        $this->addSql('ALTER TABLE voyage DROP compagnie_aerienne, DROP numero_vol, DROP nombre_places');
    }
}
