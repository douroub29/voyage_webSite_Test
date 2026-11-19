<?php

namespace App\Tests\Unit\Service;

use App\Entity\Client;
use App\Entity\Destination;
use App\Entity\Voyage;
use App\Enum\CompagnieAerienne;
use App\Repository\DestinationRepository;
use App\Repository\HotelRepository;
use App\Repository\SocieteRepository;
use App\Repository\VoucherRepository;
use App\Repository\VoyageRepository;
use App\Service\IA\OutilsCatalogue;
use App\Service\VoucherManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OutilsCatalogueTest extends TestCase
{
    private VoyageRepository $voyageRepo;
    private OutilsCatalogue $outils;

    protected function setUp(): void
    {
        $this->voyageRepo = $this->createMock(VoyageRepository::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->method('generate')->willReturnCallback(fn($route, $p) => '/voyages/' . $p['id']);

        $this->outils = new OutilsCatalogue(
            $this->voyageRepo,
            $this->createMock(DestinationRepository::class),
            $this->createMock(SocieteRepository::class),
            $this->createMock(HotelRepository::class),
            new VoucherManager($this->createMock(VoucherRepository::class), $this->createMock(EntityManagerInterface::class)),
            $url,
        );
    }

    private static function voyage(int $id, int $places): Voyage
    {
        $depart = new \DateTimeImmutable('+1 month');
        $v = (new Voyage())->setTitre("Voyage $id")
            ->setDestination((new Destination())->setNom('Paris')->setPays('France'))
            ->setDateDepart($depart)->setDateRetour($depart->modify('+7 days'))
            ->setPrix('1290.00')->setCompagnieAerienne(CompagnieAerienne::Tunisair)
            ->setNombrePlaces($places);
        (new \ReflectionProperty(Voyage::class, 'id'))->setValue($v, $id);
        return $v;
    }

    public function testRechercheFiltreSurLesPlacesEtRenvoieLesDonneesDeLaBase(): void
    {
        $this->voyageRepo->expects(self::once())->method('search')
            ->with('Paris', null, 1500.0, null)
            ->willReturn([self::voyage(1, 2), self::voyage(2, 10)]);

        $res = json_decode($this->outils->executer('rechercher_voyages', [
            'destination' => 'Paris', 'budget_max' => 1500, 'personnes' => 4,
        ], null), true);

        self::assertSame(1, $res['nombre_total']);
        self::assertSame(2, $res['voyages'][0]['id']);
        self::assertEquals(1290, $res['voyages'][0]['prix_par_personne']);
        self::assertSame(10, $res['voyages'][0]['places_restantes']);
        self::assertSame('/voyages/2', $res['voyages'][0]['lien']);
    }

    public function testDateInvalideIgnoreeEtVoyageInconnu(): void
    {
        $this->voyageRepo->expects(self::once())->method('search')->with(null, null, null, null)->willReturn([]);
        $this->outils->executer('rechercher_voyages', ['date_depart_min' => "2027-01-01'; DROP TABLE"], null);

        $res = json_decode($this->outils->executer('details_voyage', ['voyage_id' => 999], null), true);
        self::assertArrayHasKey('erreur', $res);
    }

    public function testMesReservationsReserveAuClientConnecte(): void
    {
        self::assertNotContains('mes_reservations', array_column($this->outils->definitions(false), 'name'));
        self::assertContains('mes_reservations', array_column($this->outils->definitions(true), 'name'));
        self::assertArrayHasKey('erreur', json_decode($this->outils->executer('mes_reservations', [], null), true));
        self::assertArrayHasKey('erreur', json_decode($this->outils->executer('outil_pirate', [], new Client()), true));
    }
}
