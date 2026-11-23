<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Destinations géolocalisées (météo, jours fériés, durée de vol) et gel de prix';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE destination ADD latitude DOUBLE PRECISION DEFAULT NULL, ADD longitude DOUBLE PRECISION DEFAULT NULL, ADD code_pays VARCHAR(2) DEFAULT NULL, ADD duree_vol_heures DOUBLE PRECISION DEFAULT NULL');

        // Destinations existantes : coordonnées, code pays ISO et vol approximatif depuis Tunis.
        foreach ([
            ['Paris',     48.8566,   2.3522,   'FR',  2.5],
            ['Marrakech', 31.6295,  -7.9811,   'MA',  2.5],
            ['Bali',      -8.4095, 115.1889,   'ID', 17.0],
            ['New York',  40.7128, -74.0060,   'US', 11.0],
            ['Dubaï',     25.2048,  55.2708,   'AE',  6.0],
            ['Rome',      41.9028,  12.4964,   'IT',  1.3],
        ] as [$nom, $lat, $lon, $code, $vol]) {
            $this->addSql(
                'UPDATE destination SET latitude = ?, longitude = ?, code_pays = ?, duree_vol_heures = ? WHERE nom = ?',
                [$lat, $lon, $code, $vol, $nom]
            );
        }

        $this->addSql('CREATE TABLE gel_prix (id INT AUTO_INCREMENT NOT NULL, prix_gele NUMERIC(10, 2) NOT NULL, frais NUMERIC(10, 2) NOT NULL, methode VARCHAR(255) NOT NULL, transaction_id VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, expire_le DATETIME NOT NULL, client_id INT NOT NULL, voyage_id INT NOT NULL, INDEX IDX_755CB7F019EB6921 (client_id), INDEX IDX_755CB7F068C9E5AF (voyage_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE gel_prix ADD CONSTRAINT FK_755CB7F019EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gel_prix ADD CONSTRAINT FK_755CB7F068C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD gel_prix_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849551ADC9335 FOREIGN KEY (gel_prix_id) REFERENCES gel_prix (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42C849551ADC9335 ON reservation (gel_prix_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849551ADC9335');
        $this->addSql('DROP INDEX UNIQ_42C849551ADC9335 ON reservation');
        $this->addSql('ALTER TABLE reservation DROP gel_prix_id');
        $this->addSql('ALTER TABLE gel_prix DROP FOREIGN KEY FK_755CB7F019EB6921');
        $this->addSql('ALTER TABLE gel_prix DROP FOREIGN KEY FK_755CB7F068C9E5AF');
        $this->addSql('DROP TABLE gel_prix');
        $this->addSql('ALTER TABLE destination DROP latitude, DROP longitude, DROP code_pays, DROP duree_vol_heures');
    }
}
