<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Enum\StatutReservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findRecentes(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.voyage', 'v')->addSelect('v')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByStatut(StatutReservation $statut): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumRevenusMois(): float
    {
        $debut = new \DateTime('first day of this month');
        $fin   = new \DateTime('last day of this month');

        return (float) $this->createQueryBuilder('r')
            ->select('SUM(r.prixTotal)')
            ->where('r.statut = :statut')
            ->andWhere('r.createdAt BETWEEN :debut AND :fin')
            ->setParameter('statut', StatutReservation::Confirmee)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function findVoyagesAVenir(int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.voyage', 'v')->addSelect('v')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->where('r.statut = :statut')
            ->andWhere('v.dateDepart >= :now')
            ->setParameter('statut', StatutReservation::Confirmee)
            ->setParameter('now', new \DateTime())
            ->orderBy('v.dateDepart', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function sumRevenusTotal(): float
    {
        return (float) $this->createQueryBuilder('r')
            ->select('SUM(r.prixTotal)')
            ->where('r.statut = :statut')
            ->setParameter('statut', StatutReservation::Confirmee)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }
}
