<?php

namespace App\Tests\Unit\Service;

use App\Entity\Client;
use App\Entity\Reservation;
use App\Entity\Voucher;
use App\Enum\StatutReservation;
use App\Exception\VoucherException;
use App\Repository\VoucherRepository;
use App\Service\VoucherManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VoucherManagerTest extends TestCase
{
    private VoucherRepository&MockObject $repo;
    private EntityManagerInterface&MockObject $em;
    private VoucherManager $manager;

    protected function setUp(): void
    {
        $this->repo    = $this->createMock(VoucherRepository::class);
        $this->em      = $this->createMock(EntityManagerInterface::class);
        $this->manager = new VoucherManager($this->repo, $this->em);
    }

    private static function client(int $confirmees, int $enAttente = 0, int $annulees = 0): Client
    {
        $client = new Client();
        foreach ([
            [StatutReservation::Confirmee, $confirmees],
            [StatutReservation::EnAttente, $enAttente],
            [StatutReservation::Annulee,   $annulees],
        ] as [$statut, $n]) {
            for ($i = 0; $i < $n; $i++) {
                $client->addReservation((new Reservation())->setStatut($statut));
            }
        }
        return $client;
    }

    public function testMoinsDe5ReservationsConfirmeesPasDeVoucher(): void
    {
        // Les réservations en attente ou annulées ne comptent pas.
        $client = self::client(4, enAttente: 3, annulees: 2);

        self::assertFalse($this->manager->isEligible($client));
        self::assertSame(1, $this->manager->getReservationsManquantes($client));

        $this->em->expects(self::never())->method('persist');
        $this->expectException(VoucherException::class);
        $this->manager->obtenirVoucher($client);
    }

    public function testCinqReservationsConfirmeesCreentUnVoucherDe10Pourcent(): void
    {
        $client = self::client(5);
        $this->repo->method('findPremierDisponible')->willReturn(null);
        $this->repo->method('countPourClient')->willReturn(0);
        $this->em->expects(self::once())->method('persist')->with(self::isInstanceOf(Voucher::class));

        $voucher = $this->manager->obtenirVoucher($client);

        self::assertSame($client, $voucher->getClient());
        self::assertSame(10, $voucher->getPourcentage());
        self::assertStringStartsWith('VCH-', $voucher->getCode());
        self::assertFalse($voucher->isUtilise());
    }

    /** 1er voucher -10 % (5e réservation), 2e -20 % (10e), 3e -30 % (15e), puis -30 % ensuite. */
    public function testLaRemiseAugmenteAChaqueNouveauVoucher(): void
    {
        foreach ([[10, 1, 20], [15, 2, 30], [20, 3, 30], [25, 4, 30]] as [$confirmees, $dejaEmis, $attendu]) {
            $repo = $this->createMock(\App\Repository\VoucherRepository::class);
            $repo->method('findPremierDisponible')->willReturn(null);
            $repo->method('countPourClient')->willReturn($dejaEmis);
            $manager = new VoucherManager($repo, $this->createMock(\Doctrine\ORM\EntityManagerInterface::class));

            self::assertSame($attendu, $manager->getProchainPourcentage(self::client($confirmees)), "$confirmees réservations");
            self::assertSame($attendu, $manager->obtenirVoucher(self::client($confirmees))->getPourcentage(), "$confirmees réservations");
        }
    }

    public function testPaliersDeRemise(): void
    {
        self::assertSame([10, 20, 30, 30, 30], array_map(VoucherManager::pourcentagePourRang(...), [1, 2, 3, 4, 10]));
    }

    public function testRetelechargerRenvoieLeMemeVoucherSansEnCreerUnAutre(): void
    {
        $client  = self::client(5);
        $voucher = new Voucher($client, 10);
        $this->repo->method('findPremierDisponible')->willReturn($voucher);
        $this->em->expects(self::never())->method('persist');

        self::assertSame($voucher, $this->manager->obtenirVoucher($client));
    }

    public function testVoucherDejaUtiliseIlFaut5NouvellesReservations(): void
    {
        $client = self::client(7);
        $this->repo->method('findPremierDisponible')->willReturn(null);
        $this->repo->method('countPourClient')->willReturn(1); // 1 voucher gagné, déjà émis et utilisé

        $this->expectException(VoucherException::class);
        $this->expectExceptionMessage('déjà été utilisé');
        $this->manager->obtenirVoucher($client);
    }

    public function testDixReservationsDonnentDroitAUnDeuxiemeVoucher(): void
    {
        $client = self::client(10);
        $this->repo->method('findPremierDisponible')->willReturn(null);
        $this->repo->method('countPourClient')->willReturn(1);

        self::assertSame(2, $this->manager->getNombreVouchersGagnes($client));
        self::assertInstanceOf(Voucher::class, $this->manager->obtenirVoucher($client));
    }
}
