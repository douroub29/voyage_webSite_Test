<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Chauffeur;
use App\Entity\Hotel;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Enum\StatutReservation;
use App\Enum\TypeTransfert;
use PHPUnit\Framework\TestCase;

class ReservationTest extends TestCase
{
    private static function reservation(int $personnes = 2): Reservation
    {
        return (new Reservation())
            ->setVoyage((new Voyage())->setPrix('1000.00'))
            ->setNombrePersonnes($personnes);
    }

    public function testUneNouvelleReservationEstEnAttenteAvecUneReference(): void
    {
        $reservation = new Reservation();

        self::assertSame(StatutReservation::EnAttente, $reservation->getStatut());
        self::assertMatchesRegularExpression('/^DT-[A-Z0-9]{8}$/', $reservation->getReference());
    }

    public function testLesReferencesSontUniques(): void
    {
        $references = array_map(fn() => (new Reservation())->getReference(), range(1, 200));

        self::assertCount(200, array_unique($references));
    }

    public function testPrixVoyageSeulParPersonne(): void
    {
        self::assertSame(3000.0, self::reservation(3)->calculerPrixTotal());
    }

    public function testPrixAvecHotel(): void
    {
        $reservation = self::reservation(2)
            ->setHotel((new Hotel())->setPrixParNuit('150.00'))
            ->setNombreNuits(4);

        self::assertSame(2000.0 + 600.0, $reservation->calculerPrixTotal());
    }

    public function testHotelSansNombreDeNuitsNEstPasFacture(): void
    {
        $reservation = self::reservation(1)->setHotel((new Hotel())->setPrixParNuit('150.00'));

        self::assertSame(1000.0, $reservation->calculerPrixTotal());
    }

    public function testTransfertSimple(): void
    {
        $reservation = self::reservation(1)
            ->setChauffeur((new Chauffeur())->setPrixTransfert('80.00'))
            ->setTypeTransfert(TypeTransfert::AeroportHotel);

        self::assertSame(1080.0, $reservation->calculerPrixTotal());
    }

    public function testTransfertAllerRetourEstFactureDeuxFois(): void
    {
        $reservation = self::reservation(1)
            ->setChauffeur((new Chauffeur())->setPrixTransfert('80.00'))
            ->setTypeTransfert(TypeTransfert::LesDeux);

        self::assertSame(1160.0, $reservation->calculerPrixTotal());
    }
}
