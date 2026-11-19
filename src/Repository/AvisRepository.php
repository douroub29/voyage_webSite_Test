<?php

namespace App\Repository;

use App\Entity\Avis;
use App\Enum\StatutAvis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    public function findPublies(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.client', 'c')->addSelect('c')
            ->where('a.statut = :statut')
            ->setParameter('statut', StatutAvis::Publie)
            ->orderBy('a.dateAvis', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
