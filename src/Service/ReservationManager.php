<?php

namespace App\Service;

use App\Entity\Chauffeur;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Enum\MethodePaiement;
use App\Enum\StatutPaiement;
use App\Enum\StatutReservation;
use App\Exception\ReservationException;
use App\Repository\GelPrixRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point d'entrée unique pour modifier une réservation.
 * Garantit que les places de l'avion restent cohérentes :
 *  - création   → décrémente les places du voyage, applique le voucher de fidélité et le gel de prix
 *  - annulation → remet les places en vente, rend le voucher (et le gel de prix s'il n'a pas expiré) au client
 * La ligne du voyage est verrouillée (SELECT ... FOR UPDATE) pendant l'opération
 * pour que deux clients ne puissent pas réserver la dernière place en même temps.
 */
class ReservationManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VoucherManager $voucherManager,
        private readonly ?GelPrixRepository $gelPrixRepository = null,
    ) {
    }

    /** @throws ReservationException */
    public function creer(Reservation $reservation): void
    {
        $this->em->wrapInTransaction(function () use ($reservation): void {
            $voyage = $reservation->getVoyage();
            $this->verrouiller($voyage);

            $voyage->reserverPlaces($reservation->getNombrePersonnes());

            // Le nombre de nuits n'est pas choisi par le client : c'est la durée du voyage.
            $reservation->setNombreNuits($reservation->getHotel() ? $voyage->getDureeJours() : null);

            // Promotion automatique si le client a un voucher disponible.
            $this->voucherManager->appliquerAutomatiquement($reservation);

            // Gel de prix actif du client sur ce voyage : prix le plus bas entre gelé et actuel.
            if ($this->gelPrixRepository && $reservation->getClient()
                && ($gel = $this->gelPrixRepository->findActif($reservation->getClient(), $voyage))) {
                $reservation->appliquerGelPrix($gel);
            }

            $reservation->setStatut(StatutReservation::EnAttente);
            $reservation->setPrixTotal(number_format($reservation->calculerPrixTotal(), 2, '.', ''));

            $this->em->persist($reservation);
        });
    }

    /** @throws ReservationException */
    public function annuler(Reservation $reservation): void
    {
        if ($reservation->getStatut() === StatutReservation::Annulee) {
            throw ReservationException::dejaAnnulee();
        }

        $this->em->wrapInTransaction(function () use ($reservation): void {
            $voyage = $reservation->getVoyage();
            $this->verrouiller($voyage);

            $voyage->libererPlaces($reservation->getNombrePersonnes());
            $reservation->annuler();
        });
    }

    /** Confirmation manuelle par l'admin (ex. paiement sur place). @throws ReservationException */
    public function confirmer(Reservation $reservation): void
    {
        $this->assertModifiable($reservation);

        $reservation->setStatut(StatutReservation::Confirmee);
        $this->em->flush();
    }

    /** @throws ReservationException */
    public function payer(Reservation $reservation, MethodePaiement $methode): Paiement
    {
        $this->assertModifiable($reservation);

        if ($reservation->getVoyage()->isDepartPasse()) {
            throw ReservationException::voyageParti();
        }

        $paiement = (new Paiement())
            ->setMontant($reservation->getPrixTotal())
            ->setMethode($methode)
            ->setStatut(StatutPaiement::Complete)
            ->setDatePaiement(new \DateTime())
            ->setTransactionId('TXN-' . strtoupper(uniqid()));

        $reservation->setPaiement($paiement);
        $reservation->setStatut(StatutReservation::Confirmee);

        $this->em->persist($paiement);
        $this->em->flush();

        return $paiement;
    }

    public function affecterChauffeur(Reservation $reservation, Chauffeur $chauffeur): void
    {
        $reservation->setChauffeur($chauffeur);
        $this->em->flush();
    }

    private function assertModifiable(Reservation $reservation): void
    {
        match ($reservation->getStatut()) {
            StatutReservation::Annulee   => throw ReservationException::annuleeNonModifiable(),
            StatutReservation::Confirmee => throw ReservationException::dejaConfirmee(),
            StatutReservation::EnAttente => null,
        };
    }

    private function verrouiller(object $voyage): void
    {
        // Un voyage pas encore enregistré n'a pas de ligne à verrouiller.
        if ($this->em->contains($voyage) && $voyage->getId() !== null) {
            $this->em->refresh($voyage, LockMode::PESSIMISTIC_WRITE);
        }
    }
}
