<?php

namespace App\Repository;

use App\Entity\Destination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Destination::class);
    }

    public function findAvecVoyages(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.voyages', 'v')
            ->addSelect('v')
            ->where('v.actif = true AND v.dateDepart > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('d.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
