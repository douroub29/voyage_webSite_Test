<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Programme de fidélité : table voucher et voucher appliqué sur la réservation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE voucher (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, pourcentage SMALLINT NOT NULL, created_at DATETIME NOT NULL, client_id INT NOT NULL, UNIQUE INDEX UNIQ_1392A5D877153098 (code), INDEX IDX_1392A5D819EB6921 (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE voucher ADD CONSTRAINT FK_1392A5D819EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD voucher_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495528AA1B6F FOREIGN KEY (voucher_id) REFERENCES voucher (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42C8495528AA1B6F ON reservation (voucher_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495528AA1B6F');
        $this->addSql('DROP INDEX UNIQ_42C8495528AA1B6F ON reservation');
        $this->addSql('ALTER TABLE reservation DROP voucher_id');
        $this->addSql('ALTER TABLE voucher DROP FOREIGN KEY FK_1392A5D819EB6921');
        $this->addSql('DROP TABLE voucher');
    }
}
