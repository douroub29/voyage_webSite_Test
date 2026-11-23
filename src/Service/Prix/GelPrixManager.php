<?php

namespace App\Service\Prix;

use App\Entity\Client;
use App\Entity\GelPrix;
use App\Entity\Voyage;
use App\Enum\MethodePaiement;
use App\Exception\GelPrixException;
use App\Repository\GelPrixRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gel de prix : le client paie FRAIS € pour bloquer le prix par personne pendant DUREE_JOURS jours.
 * - Si le prix monte entre-temps, DreamTravel absorbe la différence.
 * - S'il baisse, le client paie le nouveau prix, plus bas.
 * Le gel est appliqué automatiquement par ReservationManager::creer().
 */
class GelPrixManager
{
    public const FRAIS       = 10.0;
    public const DUREE_JOURS = 7;
    /** En dessous, le départ est trop proche : le client doit réserver directement. */
    private const DELAI_MIN_JOURS = 2;

    public function __construct(
        private readonly GelPrixRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function getGelActif(Client $client, Voyage $voyage): ?GelPrix
    {
        return $this->repository->findActif($client, $voyage);
    }

    /** @throws GelPrixException */
    public function geler(Client $client, Voyage $voyage, MethodePaiement $methode, ?\DateTimeInterface $maintenant = null): GelPrix
    {
        $maintenant ??= new \DateTime();

        if (!$voyage->isReservable($maintenant)) {
            throw GelPrixException::voyageNonReservable($voyage->getMotifNonReservable($maintenant));
        }
        $limite = \DateTimeImmutable::createFromInterface($maintenant)->modify('+' . self::DELAI_MIN_JOURS . ' days');
        if ($voyage->getDateDepart() <= $limite) {
            throw GelPrixException::departTropProche();
        }
        if ($existant = $this->repository->findActif($client, $voyage, $maintenant)) {
            throw GelPrixException::dejaGele($existant->getExpireLe());
        }

        // Paiement simulé des frais, comme pour les réservations.
        $gel = new GelPrix($client, $voyage, self::FRAIS, $methode, self::DUREE_JOURS, $maintenant);
        $this->em->persist($gel);
        $this->em->flush();

        return $gel;
    }

    /** @return list<GelPrix> */
    public function getGelsClient(Client $client): array
    {
        return $this->repository->findPourClient($client);
    }
}
