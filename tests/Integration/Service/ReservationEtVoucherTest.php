<?php

namespace App\Tests\Integration\Service;

use App\Entity\Client;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Enum\MethodePaiement;
use App\Enum\StatutReservation;
use App\Exception\ReservationException;
use App\Repository\ClientRepository;
use App\Repository\VoucherRepository;
use App\Repository\VoyageRepository;
use App\Service\ReservationManager;
use App\Service\VoucherManager;
use App\Service\VoyageClotureService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Parcours métier complets avec les vrais services et la base de test :
 * réservation (verrou SQL, places), paiement, programme voucher, clôture automatique.
 */
class ReservationEtVoucherTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ReservationManager $reservations;
    private VoucherManager $vouchers;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = self::getContainer();
        $this->em           = $c->get(EntityManagerInterface::class);
        $this->reservations = $c->get(ReservationManager::class);
        $this->vouchers     = $c->get(VoucherManager::class);
    }

    private function client(string $email = 'lucas@example.com'): Client
    {
        return self::getContainer()->get(ClientRepository::class)->findOneBy(['email' => $email]);
    }

    private function voyage(string $titre): Voyage
    {
        return self::getContainer()->get(VoyageRepository::class)->findOneBy(['titre' => $titre]);
    }

    private function reserverEtPayer(Client $client, Voyage $voyage, int $personnes = 1): Reservation
    {
        $r = (new Reservation())->setClient($client)->setVoyage($voyage)->setNombrePersonnes($personnes);
        $this->reservations->creer($r);
        $this->em->flush();
        $this->reservations->payer($r, MethodePaiement::from('carte'));
        $client->addReservation($r);
        return $r;
    }

    public function testReserverDecrementeLesPlacesEnBase(): void
    {
        $rome = $this->voyage('Rome Éternelle — Circuit Culturel');
        $avant = $rome->getPlacesDisponibles();

        $r = $this->reserverEtPayer($this->client(), $rome, 3);
        $this->em->clear();

        $romeRelu = $this->voyage('Rome Éternelle — Circuit Culturel');
        self::assertSame($avant - 3, $romeRelu->getPlacesDisponibles());
        self::assertSame(StatutReservation::Confirmee, $this->em->find(Reservation::class, $r->getId())->getStatut());
        self::assertSame('4350.00', $this->em->find(Reservation::class, $r->getId())->getPrixTotal()); // 3 x 1450
    }

    public function testSurventeRefuseeSansRienEnregistrer(): void
    {
        $bali = $this->voyage('Bali : L\'Île des Dieux');
        $places = $bali->getPlacesDisponibles();
        $nb = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM reservation');

        try {
            $this->reservations->creer((new Reservation())->setClient($this->client())->setVoyage($bali)->setNombrePersonnes($places + 1));
            self::fail('ReservationException attendue');
        } catch (ReservationException $e) {
            self::assertStringContainsString((string) $places, $e->getMessage());
        }

        // La transaction a été annulée (Doctrine ferme alors l'EntityManager) : rien n'est écrit.
        self::assertFalse($this->em->isOpen());
        self::assertSame($places, (int) $this->em->getConnection()->fetchOne('SELECT places_disponibles FROM voyage WHERE id = ?', [$bali->getId()]));
        self::assertSame($nb, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM reservation'));
    }

    public function testCinqReservationsPayeesDonnentUnVoucherAppliqueAutomatiquement(): void
    {
        $client = $this->client();
        $rome   = $this->voyage('Rome Éternelle — Circuit Culturel');

        for ($i = 1; $i <= 4; $i++) {
            $this->reserverEtPayer($client, $rome);
            self::assertFalse($this->vouchers->isEligible($client), "pas éligible après $i réservation(s)");
        }
        $this->reserverEtPayer($client, $rome);
        self::assertTrue($this->vouchers->isEligible($client));

        // "Télécharger voucher" : ticket créé et stocké en base
        $voucher = $this->vouchers->obtenirVoucher($client);
        self::assertNotNull(self::getContainer()->get(VoucherRepository::class)->findPremierDisponible($client));
        self::assertSame($voucher, $this->vouchers->obtenirVoucher($client), 'retélécharger renvoie le même ticket');

        // Réservation suivante : -10 % automatique
        $suivante = (new Reservation())->setClient($client)->setVoyage($rome)->setNombrePersonnes(2);
        $this->reservations->creer($suivante);
        $this->em->flush();
        self::assertSame($voucher, $suivante->getVoucher());
        self::assertSame('2610.00', $suivante->getPrixTotal()); // 2 x 1450 = 2900, -10 %
        self::assertNull(self::getContainer()->get(VoucherRepository::class)->findPremierDisponible($client));

        // Annulation : le voucher redevient disponible
        $this->reservations->annuler($suivante);
        $this->em->flush();
        self::assertSame($voucher, self::getContainer()->get(VoucherRepository::class)->findPremierDisponible($client));
    }

    public function testClotureAutomatiqueDesVoyagesPartis(): void
    {
        $marrakech = $this->voyage('Escapade Mystique à Marrakech'); // une réservation "en attente" (Lucas) dans les fixtures
        $placesAvant = $marrakech->getPlacesDisponibles();
        $marrakech->setDateDepart(new \DateTime('today'));
        $this->em->flush();

        $resultat = self::getContainer()->get(VoyageClotureService::class)->cloturer();

        self::assertCount(1, $resultat);
        self::assertSame(1, $resultat[0]['annulees']);
        $this->em->clear();
        $relu = $this->voyage('Escapade Mystique à Marrakech');
        self::assertFalse($relu->isActif());
        self::assertSame($placesAvant + 1, $relu->getPlacesDisponibles());
        self::assertSame(StatutReservation::Annulee, $relu->getReservations()->first()->getStatut());
    }
}
