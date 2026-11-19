<?php

namespace App\Service;

use App\Entity\Avis;
use App\Entity\Chauffeur;
use App\Entity\Client;
use App\Entity\Hotel;
use App\Enum\StatutAvis;
use Doctrine\ORM\EntityManagerInterface;

/** Règles de gestion des avis clients (couche Modèle, hors contrôleurs). */
class AvisService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** Avis général déposé depuis l'accueil : publié immédiatement. */
    public function publier(Avis $avis, Client $client): void
    {
        $avis->setClient($client)->setStatut(StatutAvis::Publie);
        $this->em->persist($avis);
        $this->em->flush();
    }

    /** Avis sur un hôtel ou un chauffeur déposé depuis "Mes réservations" : soumis à modération. */
    public function soumettre(Client $client, int $note, string $commentaire, ?string $cible, int $cibleId): Avis
    {
        $avis = (new Avis())
            ->setClient($client)
            ->setNote(max(1, min(5, $note)))
            ->setCommentaire($commentaire)
            ->setStatut(StatutAvis::EnAttente);

        if ($cible === 'hotel' && ($hotel = $this->em->find(Hotel::class, $cibleId))) {
            $avis->setHotel($hotel);
        } elseif ($cible === 'chauffeur' && ($chauffeur = $this->em->find(Chauffeur::class, $cibleId))) {
            $avis->setChauffeur($chauffeur);
        }

        $this->em->persist($avis);
        $this->em->flush();

        return $avis;
    }
}
