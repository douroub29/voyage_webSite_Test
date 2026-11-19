<?php

namespace App\Service;

use App\Entity\Voyage;
use App\Enum\StatutReservation;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Clôture les voyages dont la date de départ est atteinte :
 *  - le voyage passe automatiquement en "inactif"
 *  - ses réservations encore "en attente" (non payées) sont annulées
 *
 * Appelé automatiquement à chaque requête (VoyageClotureSubscriber) et
 * par la commande app:voyages:cloturer.
 */
class VoyageClotureService
{
    public function __construct(
        private readonly VoyageRepository $voyageRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return array<int, array{voyage: Voyage, annulees: int}> voyages clôturés
     */
    public function cloturer(?\DateTimeInterface $aujourdhui = null, bool $dryRun = false): array
    {
        $aujourdhui ??= new \DateTimeImmutable('today');
        $voyages = $this->voyageRepository->findActifsDepartAtteint($aujourdhui);

        $resultat = [];
        foreach ($voyages as $voyage) {
            $annulees = 0;
            foreach ($voyage->getReservations() as $reservation) {
                if ($reservation->getStatut() === StatutReservation::EnAttente) {
                    $annulees++;
                    if (!$dryRun) {
                        $reservation->annuler(); // rend aussi le voucher éventuel au client
                        $voyage->libererPlaces($reservation->getNombrePersonnes());
                    }
                }
            }
            if (!$dryRun) {
                $voyage->setActif(false);
            }
            $resultat[] = ['voyage' => $voyage, 'annulees' => $annulees];
        }

        if ($resultat && !$dryRun) {
            $this->em->flush();
        }

        return $resultat;
    }
}
