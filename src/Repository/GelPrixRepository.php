<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\GelPrix;
use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GelPrix> */
class GelPrixRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GelPrix::class);
    }

    /** Gel non utilisé et non expiré du client pour ce voyage. */
    public function findActif(Client $client, Voyage $voyage, ?\DateTimeInterface $maintenant = null): ?GelPrix
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.reservation', 'r')
            ->where('g.client = :client')
            ->andWhere('g.voyage = :voyage')
            ->andWhere('r.id IS NULL')
            ->andWhere('g.expireLe > :maintenant')
            ->setParameter('client', $client)
            ->setParameter('voyage', $voyage)
            ->setParameter('maintenant', $maintenant ?? new \DateTime())
            ->orderBy('g.prixGele', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<GelPrix> gels du client, du plus récent au plus ancien */
    public function findPourClient(Client $client): array
    {
        return $this->createQueryBuilder('g')
            ->addSelect('v', 'r')
            ->join('g.voyage', 'v')
            ->leftJoin('g.reservation', 'r')
            ->where('g.client = :client')
            ->setParameter('client', $client)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
