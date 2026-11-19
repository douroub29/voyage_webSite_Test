<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119195914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les sociétés de voyage, les associe aux voyages existants et renseigne les photos manquantes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE societe (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, UNIQUE INDEX UNIQ_19653DBD6C6E55B5 (nom), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE voyage ADD societe_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D8955FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3F9D8955FCF77503 ON voyage (societe_id)');

        // Sociétés de départ
        $this->addSql("INSERT INTO societe (nom) VALUES ('Horizon Évasion'), ('Atlas Voyages'), ('Globe Trotter Agency')");

        // Répartition des voyages existants selon leur destination
        $repartition = [
            'Horizon Évasion'      => ['Paris', 'Rome'],
            'Atlas Voyages'        => ['Marrakech', 'Dubaï'],
            'Globe Trotter Agency' => ['Bali', 'New York'],
        ];
        foreach ($repartition as $societe => $destinations) {
            $this->addSql(
                'UPDATE voyage v JOIN destination d ON d.id = v.destination_id
                 SET v.societe_id = (SELECT s.id FROM societe s WHERE s.nom = ?)
                 WHERE d.nom IN (?, ?)',
                [$societe, ...$destinations]
            );
        }

        // Photos (public/uploads/voyages) pour les voyages qui n'en ont pas
        $photos = [
            'Paris' => 'paris', 'Marrakech' => 'marrakech', 'Bali' => 'bali',
            'New York' => 'new-york', 'Dubaï' => 'dubai', 'Rome' => 'rome',
        ];
        foreach ($photos as $destination => $fichier) {
            $this->addSql(
                'UPDATE voyage v JOIN destination d ON d.id = v.destination_id
                 SET v.photo = ? WHERE v.photo IS NULL AND d.nom = ?',
                ['voyages/' . $fichier . '.jpg', $destination]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D8955FCF77503');
        $this->addSql('DROP INDEX IDX_3F9D8955FCF77503 ON voyage');
        $this->addSql('ALTER TABLE voyage DROP societe_id');
        $this->addSql('DROP TABLE societe');
    }
}
