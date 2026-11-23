<?php

namespace App\Tests\Unit\Service;

use App\Entity\Client;
use App\Entity\Destination;
use App\Entity\GelPrix;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Enum\CompagnieAerienne;
use App\Enum\MethodePaiement;
use App\Exception\GelPrixException;
use App\Repository\GelPrixRepository;
use App\Service\Prix\GelPrixManager;
use App\Service\Prix\PrevisionPrix;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class GelEtPrevisionPrixTest extends TestCase
{
    private static function voyage(string $prix = '1000', string $depart = '+60 days', int $places = 20): Voyage
    {
        $d = new \DateTimeImmutable($depart);
        return (new Voyage())
            ->setTitre('V')
            ->setDestination((new Destination())->setNom('X')->setPays('Y'))
            ->setDateDepart($d)
            ->setDateRetour($d->modify('+7 days'))
            ->setPrix($prix)
            ->setCompagnieAerienne(CompagnieAerienne::Tunisair)
            ->setNombrePlaces($places);
    }

    /** Réservation récente de $personnes places (compte dans le rythme des ventes). */
    private static function vendre(Voyage $voyage, int $personnes, string $quand = '-2 days'): void
    {
        $voyage->reserverPlaces($personnes);
        $voyage->getReservations()->add(
            (new Reservation())->setVoyage($voyage)->setNombrePersonnes($personnes)->setCreatedAt(new \DateTime($quand))
        );
    }

    // ── Gel de prix ─────────────────────────────────────────────

    public function testLePrixGelePlusBasEstAppliqueSiLePrixMonte(): void
    {
        $voyage = self::voyage('1000');
        $gel    = new GelPrix(new Client(), $voyage, 10, MethodePaiement::Carte, 7);

        $voyage->setPrix('1200');
        $reservation = (new Reservation())->setVoyage($voyage)->setNombrePersonnes(2);
        $reservation->appliquerGelPrix($gel);

        self::assertSame(1000.0, $reservation->getPrixUnitaireVoyage());
        self::assertSame(2000.0, $reservation->calculerPrixTotal());
        self::assertSame(400.0, $reservation->getEconomieGelPrix());
        self::assertTrue($gel->isUtilise());
    }

    public function testLeClientPaieLeNouveauPrixSiLePrixBaisse(): void
    {
        $voyage = self::voyage('1000');
        $gel    = new GelPrix(new Client(), $voyage, 10, MethodePaiement::Carte, 7);

        $voyage->setPrix('900');
        $reservation = (new Reservation())->setVoyage($voyage)->setNombrePersonnes(1);
        $reservation->appliquerGelPrix($gel);

        self::assertSame(900.0, $reservation->calculerPrixTotal());
        self::assertSame(0.0, $reservation->getEconomieGelPrix());
    }

    public function testLAnnulationRendLeGelDisponible(): void
    {
        $voyage = self::voyage();
        $gel    = new GelPrix(new Client(), $voyage, 10, MethodePaiement::Carte, 7);
        $reservation = (new Reservation())->setVoyage($voyage);
        $reservation->appliquerGelPrix($gel);

        $reservation->annuler();

        self::assertNull($reservation->getGelPrix());
        self::assertTrue($gel->isActif());
    }

    public function testLeGelExpireApresSaDureeEtJamaisApresLeDepart(): void
    {
        $maintenant = new \DateTimeImmutable('2027-01-01 10:00');
        $gel = new GelPrix(new Client(), self::voyage(depart: '2027-03-01'), 10, MethodePaiement::Carte, 7, $maintenant);
        self::assertSame('2027-01-08 10:00', $gel->getExpireLe()->format('Y-m-d H:i'));
        self::assertTrue($gel->isActif($maintenant->modify('+6 days')));
        self::assertFalse($gel->isActif($maintenant->modify('+7 days')));

        $gelCourt = new GelPrix(new Client(), self::voyage(depart: '2027-01-04'), 10, MethodePaiement::Carte, 7, $maintenant);
        self::assertSame('2027-01-04', $gelCourt->getExpireLe()->format('Y-m-d'));
    }

    public function testGelerCreeUnGelAuPrixActuel(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $manager = new GelPrixManager($this->createMock(GelPrixRepository::class), $em);

        $gel = $manager->geler(new Client(), self::voyage('1290'), MethodePaiement::Paypal);

        self::assertSame('1290', $gel->getPrixGele());
        self::assertSame('10.00', $gel->getFrais());
        self::assertStringStartsWith('GEL-', $gel->getTransactionId());
    }

    public function testOnNePeutPasGelerDeuxFoisLeMemeVoyage(): void
    {
        $voyage = self::voyage();
        $repo = $this->createMock(GelPrixRepository::class);
        $repo->method('findActif')->willReturn(new GelPrix(new Client(), $voyage, 10, MethodePaiement::Carte, 7));

        $this->expectException(GelPrixException::class);
        (new GelPrixManager($repo, $this->createMock(EntityManagerInterface::class)))->geler(new Client(), $voyage, MethodePaiement::Carte);
    }

    public function testOnNePeutPasGelerUnVoyageCompletOuQuiPartBientot(): void
    {
        $manager = new GelPrixManager($this->createMock(GelPrixRepository::class), $this->createMock(EntityManagerInterface::class));

        $complet = self::voyage(places: 2);
        $complet->reserverPlaces(2);
        try {
            $manager->geler(new Client(), $complet, MethodePaiement::Carte);
            self::fail('Un voyage complet ne doit pas pouvoir être gelé.');
        } catch (GelPrixException) {
        }

        $this->expectException(GelPrixException::class);
        $manager->geler(new Client(), self::voyage(depart: '+1 day'), MethodePaiement::Carte);
    }

    // ── Prévision ───────────────────────────────────────────────

    public function testForteDemandeProcheDuDepartPrevoitUneHausse(): void
    {
        $voyage = self::voyage(depart: '+20 days');
        self::vendre($voyage, 14);

        $p = (new PrevisionPrix())->prevoir($voyage);

        self::assertSame('hausse', $p['tendance']);
        self::assertGreaterThan(1000, $p['prix_estime']);
        self::assertGreaterThan(50, $p['probabilite_hausse']);
    }

    public function testAvionPeuRempliAuDernierMomentPrevoitUneBaisse(): void
    {
        $voyage = self::voyage(depart: '+10 days');
        self::vendre($voyage, 1, '-40 days');

        $p = (new PrevisionPrix())->prevoir($voyage);

        self::assertSame('baisse', $p['tendance']);
        self::assertLessThan(50, $p['probabilite_hausse']);
    }

    public function testDepartLointainEtDemandeFaibleEstStable(): void
    {
        $voyage = self::voyage(depart: '+150 days');
        self::vendre($voyage, 4, '-60 days');

        self::assertSame('stable', (new PrevisionPrix())->prevoir($voyage)['tendance']);
    }
}
