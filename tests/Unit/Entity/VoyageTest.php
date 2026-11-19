<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Enum\CompagnieAerienne;
use App\Enum\StatutReservation;
use App\Exception\ReservationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VoyageTest extends TestCase
{
    private const AUJOURDHUI = '2026-06-15';

    private static function voyage(int $places = 10, string $depart = '2026-07-01', bool $actif = true): Voyage
    {
        $depart = new \DateTimeImmutable($depart);

        return (new Voyage())
            ->setTitre('Test')
            ->setDateDepart($depart)
            ->setDateRetour($depart->modify('+7 days'))
            ->setPrix('1000.00')
            ->setCompagnieAerienne(CompagnieAerienne::Tunisair)
            ->setNombrePlaces($places)
            ->setActif($actif);
    }

    private static function aujourdhui(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::AUJOURDHUI);
    }

    // ── Capacité de l'avion ──────────────────────────────────────────

    public function testUnNouveauVoyageAToutesSesPlacesDisponibles(): void
    {
        $voyage = self::voyage(180);

        self::assertSame(180, $voyage->getNombrePlaces());
        self::assertSame(180, $voyage->getPlacesDisponibles());
        self::assertSame(0, $voyage->getPlacesReservees());
        self::assertSame(0, $voyage->getTauxRemplissage());
    }

    public function testReserverDecrementeLesPlaces(): void
    {
        $voyage = self::voyage(10);

        $voyage->reserverPlaces(3, self::aujourdhui());
        $voyage->reserverPlaces(2, self::aujourdhui());

        self::assertSame(5, $voyage->getPlacesDisponibles());
        self::assertSame(5, $voyage->getPlacesReservees());
        self::assertSame(50, $voyage->getTauxRemplissage());
    }

    public function testOnPeutReserverLaDernierePlace(): void
    {
        $voyage = self::voyage(2);

        $voyage->reserverPlaces(2, self::aujourdhui());

        self::assertTrue($voyage->isComplet());
        self::assertFalse($voyage->isReservable(self::aujourdhui()));
        self::assertSame('Complet : plus de places disponibles', $voyage->getMotifNonReservable(self::aujourdhui()));
    }

    public function testImpossibleDeReserverPlusQueLesPlacesRestantes(): void
    {
        $voyage = self::voyage(4);
        $voyage->reserverPlaces(3, self::aujourdhui());

        $this->expectException(ReservationException::class);
        $this->expectExceptionMessage('Il ne reste que 1 place(s)');

        $voyage->reserverPlaces(2, self::aujourdhui());
    }

    public function testUneSurventeNeModifiePasLesPlaces(): void
    {
        $voyage = self::voyage(4);

        try {
            $voyage->reserverPlaces(5, self::aujourdhui());
            self::fail('Une exception était attendue');
        } catch (ReservationException) {
        }

        self::assertSame(4, $voyage->getPlacesDisponibles());
    }

    public function testVoyageCompletRenvoieLeMessageComplet(): void
    {
        $voyage = self::voyage(1);
        $voyage->reserverPlaces(1, self::aujourdhui());

        $this->expectExceptionMessage('Ce voyage est complet.');
        $voyage->reserverPlaces(1, self::aujourdhui());
    }

    #[DataProvider('nombresInvalides')]
    public function testNombreDePersonnesDoitEtrePositif(int $nombre): void
    {
        $this->expectException(ReservationException::class);
        self::voyage()->reserverPlaces($nombre, self::aujourdhui());
    }

    public static function nombresInvalides(): iterable
    {
        yield 'zéro' => [0];
        yield 'négatif' => [-2];
    }

    public function testAnnulerRemetLesPlacesEnVente(): void
    {
        $voyage = self::voyage(10);
        $voyage->reserverPlaces(4, self::aujourdhui());

        $voyage->libererPlaces(4);

        self::assertSame(10, $voyage->getPlacesDisponibles());
    }

    public function testLibererNeDepassePasLaCapaciteDeLAvion(): void
    {
        $voyage = self::voyage(10);

        $voyage->libererPlaces(5);

        self::assertSame(10, $voyage->getPlacesDisponibles());
    }

    public function testAugmenterLaCapaciteConserveLesPlacesReservees(): void
    {
        $voyage = self::voyage(10);
        $voyage->reserverPlaces(6, self::aujourdhui());

        $voyage->setNombrePlaces(20);

        self::assertSame(14, $voyage->getPlacesDisponibles());
        self::assertSame(6, $voyage->getPlacesReservees());
    }

    public function testReduireLaCapaciteSousLesPlacesReserveesRendLeVoyageInvalide(): void
    {
        $voyage = self::voyage(10);
        $voyage->reserverPlaces(8, self::aujourdhui());

        $voyage->setNombrePlaces(5);

        // La validation (VoyageValidationTest) bloque ce cas ; ici on vérifie l'état
        self::assertSame(-3, $voyage->getPlacesDisponibles());
        self::assertSame(0, $voyage->getPlacesRestantes());
    }

    // ── Dates : fermeture des réservations ───────────────────────────

    #[DataProvider('datesDeDepart')]
    public function testFermetureDesReservationsSelonLaDateDeDepart(string $depart, bool $reservable): void
    {
        $voyage = self::voyage(10, $depart);

        self::assertSame($reservable, $voyage->isReservable(self::aujourdhui()));
        self::assertSame(!$reservable, $voyage->isDepartPasse(self::aujourdhui()));
    }

    public static function datesDeDepart(): iterable
    {
        yield 'départ demain : ouvert'     => ['2026-06-16', true];
        yield 'départ aujourd\'hui : fermé' => ['2026-06-15', false];
        yield 'départ hier : fermé'        => ['2026-06-14', false];
        yield 'départ l\'an passé : fermé' => ['2025-06-15', false];
    }

    public function testLHeureDuJourNInfluePasSurLaFermeture(): void
    {
        $voyage = self::voyage(10, '2026-06-16');

        self::assertTrue($voyage->isReservable(new \DateTimeImmutable('2026-06-15 23:59:59')));
        self::assertFalse($voyage->isReservable(new \DateTimeImmutable('2026-06-16 00:00:01')));
    }

    public function testImpossibleDeReserverUnVoyageParti(): void
    {
        $voyage = self::voyage(10, '2026-06-10');

        $this->expectException(ReservationException::class);
        $this->expectExceptionMessage('date de départ');

        $voyage->reserverPlaces(1, self::aujourdhui());
    }

    public function testImpossibleDeReserverUnVoyageInactif(): void
    {
        $voyage = self::voyage(10, '2026-07-01', actif: false);

        self::assertFalse($voyage->isReservable(self::aujourdhui()));
        $this->expectException(ReservationException::class);

        $voyage->reserverPlaces(1, self::aujourdhui());
    }

    public function testLeMotifDepartPassePrimeSurLesAutres(): void
    {
        $voyage = self::voyage(1, '2026-06-01', actif: false);

        self::assertSame('Réservations fermées : voyage déjà parti', $voyage->getMotifNonReservable(self::aujourdhui()));
    }

    public function testUnVoyageReservableNAPasDeMotif(): void
    {
        self::assertNull(self::voyage()->getMotifNonReservable(self::aujourdhui()));
    }

    // ── Divers ───────────────────────────────────────────────────────

    public function testDureeEnJours(): void
    {
        self::assertSame(7, self::voyage()->getDureeJours());
    }

    public function testNumeroDeVolEstNormalise(): void
    {
        $voyage = self::voyage()->setNumeroVol('  tu 712 ');

        self::assertSame('TU 712', $voyage->getNumeroVol());
        self::assertNull($voyage->setNumeroVol('   ')->getNumeroVol());
    }

    public function testCoherenceAvecLesReservations(): void
    {
        $voyage = self::voyage(10);
        foreach ([[2, StatutReservation::Confirmee], [3, StatutReservation::EnAttente], [4, StatutReservation::Annulee]] as [$n, $statut]) {
            $voyage->getReservations()->add((new Reservation())->setNombrePersonnes($n)->setStatut($statut));
            if ($statut !== StatutReservation::Annulee) {
                $voyage->reserverPlaces($n, self::aujourdhui());
            }
        }

        self::assertSame(5, $voyage->compterPlacesReserveesDepuisReservations());
        self::assertSame($voyage->getPlacesReservees(), $voyage->compterPlacesReserveesDepuisReservations());
    }
}
