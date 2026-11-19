<?php

namespace App\Tests\Unit\Service;

use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Enum\CompagnieAerienne;
use App\Enum\MethodePaiement;
use App\Enum\StatutPaiement;
use App\Enum\StatutReservation;
use App\Exception\ReservationException;
use App\Entity\Client;
use App\Entity\Voucher;
use App\Repository\VoucherRepository;
use App\Service\ReservationManager;
use App\Service\VoucherManager;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReservationManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private VoucherRepository&MockObject $voucherRepo;
    private ReservationManager $manager;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('wrapInTransaction')->willReturnCallback(fn(callable $fn) => $fn($this->em));
        $this->voucherRepo = $this->createMock(VoucherRepository::class);
        $this->manager = new ReservationManager($this->em, new VoucherManager($this->voucherRepo, $this->em));
    }

    private static function voyage(int $places = 10, string $depart = '+1 month', ?int $id = null): Voyage
    {
        $depart = new \DateTimeImmutable($depart);
        $voyage = (new Voyage())
            ->setTitre('Test')
            ->setDateDepart($depart)
            ->setDateRetour($depart->modify('+7 days'))
            ->setPrix('500.00')
            ->setCompagnieAerienne(CompagnieAerienne::Tunisair)
            ->setNombrePlaces($places);

        if ($id !== null) {
            (new \ReflectionProperty(Voyage::class, 'id'))->setValue($voyage, $id);
        }

        return $voyage;
    }

    private static function reservation(Voyage $voyage, int $personnes): Reservation
    {
        return (new Reservation())->setVoyage($voyage)->setNombrePersonnes($personnes);
    }

    // ── Création ─────────────────────────────────────────────────────

    public function testCreerDecrementeLesPlacesEtCalculeLePrix(): void
    {
        $voyage      = self::voyage(10);
        $reservation = self::reservation($voyage, 3);

        $this->em->expects(self::once())->method('persist')->with($reservation);

        $this->manager->creer($reservation);

        self::assertSame(7, $voyage->getPlacesDisponibles());
        self::assertSame('1500.00', $reservation->getPrixTotal());
        self::assertSame(StatutReservation::EnAttente, $reservation->getStatut());
    }

    public function testCreerVerrouilleLaLigneDuVoyageEnBase(): void
    {
        $voyage = self::voyage(10, id: 42);

        $this->em->method('contains')->with($voyage)->willReturn(true);
        $this->em->expects(self::once())->method('refresh')->with($voyage, LockMode::PESSIMISTIC_WRITE);

        $this->manager->creer(self::reservation($voyage, 1));
    }

    public function testCreerRefuseLaSurventeEtNePersisteRien(): void
    {
        $voyage = self::voyage(2);

        $this->em->expects(self::never())->method('persist');
        $this->expectException(ReservationException::class);

        try {
            $this->manager->creer(self::reservation($voyage, 3));
        } finally {
            self::assertSame(2, $voyage->getPlacesDisponibles());
        }
    }

    public function testCreerRefuseUnVoyageParti(): void
    {
        $this->expectException(ReservationException::class);
        $this->expectExceptionMessage('date de départ');

        $this->manager->creer(self::reservation(self::voyage(10, 'today'), 1));
    }

    // ── Annulation ───────────────────────────────────────────────────

    public function testAnnulerRemetLesPlacesEnVente(): void
    {
        $voyage      = self::voyage(10);
        $reservation = self::reservation($voyage, 4);
        $this->manager->creer($reservation);

        $this->manager->annuler($reservation);

        self::assertSame(StatutReservation::Annulee, $reservation->getStatut());
        self::assertSame(10, $voyage->getPlacesDisponibles());
    }

    public function testOnNePeutPasAnnulerDeuxFois(): void
    {
        $voyage      = self::voyage(10);
        $reservation = self::reservation($voyage, 4);
        $this->manager->creer($reservation);
        $this->manager->annuler($reservation);

        $this->expectExceptionMessage('déjà annulée');

        try {
            $this->manager->annuler($reservation);
        } finally {
            // Les places n'ont pas été libérées une deuxième fois
            self::assertSame(10, $voyage->getPlacesDisponibles());
        }
    }

    // ── Voucher fidélité ─────────────────────────────────────────────

    public function testCreerAppliqueAutomatiquementLeVoucherDisponible(): void
    {
        $client  = new Client();
        $voucher = new Voucher($client, 10);
        $this->voucherRepo->method('findPremierDisponible')->with($client)->willReturn($voucher);

        $reservation = self::reservation(self::voyage(10), 3)->setClient($client); // 3 x 500 = 1500
        $this->manager->creer($reservation);

        self::assertSame($voucher, $reservation->getVoucher());
        self::assertTrue($voucher->isUtilise());
        self::assertSame('1350.00', $reservation->getPrixTotal()); // -10 %
    }

    public function testSansVoucherLePrixEstPlein(): void
    {
        $this->voucherRepo->method('findPremierDisponible')->willReturn(null);

        $reservation = self::reservation(self::voyage(10), 3)->setClient(new Client());
        $this->manager->creer($reservation);

        self::assertNull($reservation->getVoucher());
        self::assertSame('1500.00', $reservation->getPrixTotal());
    }

    public function testAnnulerRendLeVoucherAuClient(): void
    {
        $client  = new Client();
        $voucher = new Voucher($client, 10);
        $this->voucherRepo->method('findPremierDisponible')->willReturn($voucher);

        $reservation = self::reservation(self::voyage(10), 1)->setClient($client);
        $this->manager->creer($reservation);
        $this->manager->annuler($reservation);

        self::assertNull($reservation->getVoucher());
        self::assertFalse($voucher->isUtilise());
    }

    // ── Confirmation / paiement ──────────────────────────────────────

    public function testConfirmerUneReservationEnAttente(): void
    {
        $reservation = self::reservation(self::voyage(), 1);

        $this->manager->confirmer($reservation);

        self::assertSame(StatutReservation::Confirmee, $reservation->getStatut());
    }

    public function testOnNePeutPasConfirmerUneReservationAnnulee(): void
    {
        $reservation = self::reservation(self::voyage(), 1)->setStatut(StatutReservation::Annulee);

        $this->expectException(ReservationException::class);
        $this->manager->confirmer($reservation);
    }

    public function testPayerConfirmeEtCreeLePaiement(): void
    {
        $reservation = self::reservation(self::voyage(), 2)->setPrixTotal('1000.00');

        $this->em->expects(self::once())->method('persist')->with(self::isInstanceOf(Paiement::class));

        $paiement = $this->manager->payer($reservation, MethodePaiement::from('carte'));

        self::assertSame(StatutReservation::Confirmee, $reservation->getStatut());
        self::assertSame($paiement, $reservation->getPaiement());
        self::assertSame('1000.00', $paiement->getMontant());
        self::assertSame(StatutPaiement::Complete, $paiement->getStatut());
        self::assertStringStartsWith('TXN-', $paiement->getTransactionId());
    }

    public function testOnNePeutPasPayerDeuxFois(): void
    {
        $reservation = self::reservation(self::voyage(), 1)->setStatut(StatutReservation::Confirmee);

        $this->expectExceptionMessage('déjà confirmée');
        $this->manager->payer($reservation, MethodePaiement::from('carte'));
    }

    public function testOnNePeutPasPayerUneReservationAnnulee(): void
    {
        $reservation = self::reservation(self::voyage(), 1)->setStatut(StatutReservation::Annulee);

        $this->expectException(ReservationException::class);
        $this->manager->payer($reservation, MethodePaiement::from('carte'));
    }

    public function testOnNePeutPasPayerApresLeDepart(): void
    {
        $reservation = self::reservation(self::voyage(10, '-1 day'), 1);

        $this->expectExceptionMessage('date de départ');
        $this->manager->payer($reservation, MethodePaiement::from('carte'));
    }
}
