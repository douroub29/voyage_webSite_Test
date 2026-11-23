<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Reservation;
use App\Entity\Voucher;
use App\Exception\VoucherException;
use App\Repository\VoucherRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Programme de fidélité :
 *  - chaque tranche de SEUIL_RESERVATIONS réservations confirmées donne droit à 1 voucher ;
 *  - la remise augmente à chaque voucher : -10 % (5e réservation), -20 % (10e), puis -30 % (15e et suivantes) ;
 *  - le client "télécharge" son voucher : un ticket de remise est créé et stocké sur son compte ;
 *  - à sa prochaine réservation, le voucher disponible est appliqué automatiquement.
 */
class VoucherManager
{
    public const SEUIL_RESERVATIONS  = 5;
    /** Remise du 1er, 2e, 3e voucher… ; le dernier palier s'applique aux vouchers suivants. */
    public const PALIERS_REMISE      = [10, 20, 30];

    public function __construct(
        private readonly VoucherRepository $voucherRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /** Nombre total de vouchers gagnés grâce aux réservations confirmées. */
    public function getNombreVouchersGagnes(Client $client): int
    {
        return intdiv($client->getNombreReservationsConfirmees(), self::SEUIL_RESERVATIONS);
    }

    /** Le client a-t-il accès à l'option voucher ? (au moins SEUIL réservations confirmées) */
    public function isEligible(Client $client): bool
    {
        return $this->getNombreVouchersGagnes($client) > 0;
    }

    /** Réservations confirmées encore nécessaires pour gagner le prochain voucher. */
    public function getReservationsManquantes(Client $client): int
    {
        $reste = $client->getNombreReservationsConfirmees() % self::SEUIL_RESERVATIONS;
        return self::SEUIL_RESERVATIONS - $reste;
    }

    /** Remise du voucher de rang $rang (1 = premier voucher gagné). */
    public static function pourcentagePourRang(int $rang): int
    {
        return self::PALIERS_REMISE[min(max($rang, 1), count(self::PALIERS_REMISE)) - 1];
    }

    /** Remise du voucher disponible, sinon de celui que le client gagnera ensuite. */
    public function getProchainPourcentage(Client $client): int
    {
        return $this->getVoucherDisponible($client)?->getPourcentage()
            ?? self::pourcentagePourRang($this->voucherRepository->countPourClient($client) + 1);
    }

    public function getVoucherDisponible(Client $client): ?Voucher
    {
        return $this->voucherRepository->findPremierDisponible($client);
    }

    /**
     * Renvoie le voucher disponible du client, ou en crée un s'il en a gagné un nouveau.
     *
     * @throws VoucherException si le client n'a pas (ou plus) droit à un voucher
     */
    public function obtenirVoucher(Client $client): Voucher
    {
        if (!$this->isEligible($client)) {
            throw VoucherException::nonEligible($this->getReservationsManquantes($client));
        }

        if ($voucher = $this->getVoucherDisponible($client)) {
            return $voucher;
        }

        $dejaEmis = $this->voucherRepository->countPourClient($client);
        if ($dejaEmis >= $this->getNombreVouchersGagnes($client)) {
            throw VoucherException::dejaUtilise($this->getReservationsManquantes($client));
        }

        $voucher = new Voucher($client, self::pourcentagePourRang($dejaEmis + 1));
        $this->em->persist($voucher);
        $this->em->flush();

        return $voucher;
    }

    /** Applique automatiquement le voucher disponible du client à une nouvelle réservation. */
    public function appliquerAutomatiquement(Reservation $reservation): ?Voucher
    {
        $client = $reservation->getClient();
        if (!$client || $reservation->getVoucher()) {
            return $reservation->getVoucher();
        }

        $voucher = $this->getVoucherDisponible($client);
        $reservation->appliquerVoucher($voucher);

        return $voucher;
    }
}
