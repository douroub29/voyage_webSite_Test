<?php

namespace App\Repository;

use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VoyageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voyage::class);
    }

    public function findActifs(): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.destination', 'd')
            ->addSelect('d')
            ->where('v.actif = true')
            ->andWhere('v.dateDepart > :now')
            ->setParameter('now', new \DateTimeImmutable('today'))
            ->orderBy('v.dateDepart', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function search(?string $destination, ?\DateTimeInterface $dateDepart, ?float $budgetMax, ?string $societe = null): array
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.destination', 'd')
            ->addSelect('d')
            ->leftJoin('v.societe', 's')
            ->addSelect('s')
            ->where('v.actif = true')
            ->andWhere('v.dateDepart > :now')
            ->setParameter('now', new \DateTimeImmutable('today'));

        if ($destination) {
            $qb->andWhere('d.nom LIKE :dest OR d.pays LIKE :dest OR v.titre LIKE :dest')
               ->setParameter('dest', '%' . $destination . '%');
        }

        if ($societe) {
            $qb->andWhere('s.nom LIKE :societe')
               ->setParameter('societe', '%' . $societe . '%');
        }

        if ($dateDepart) {
            $qb->andWhere('v.dateDepart >= :dateDepart')
               ->setParameter('dateDepart', $dateDepart);
        }

        if ($budgetMax) {
            $qb->andWhere('v.prix <= :budget')
               ->setParameter('budget', $budgetMax);
        }

        return $qb->orderBy('v.dateDepart', 'ASC')->getQuery()->getResult();
    }

    public function findVedettes(int $limit = 6): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.destination', 'd')
            ->addSelect('d')
            ->where('v.actif = true')
            ->andWhere('v.dateDepart > :now')
            ->setParameter('now', new \DateTimeImmutable('today'))
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Voyages encore actifs dont la date de départ est atteinte (à clôturer).
     *
     * @return Voyage[]
     */
    public function findActifsDepartAtteint(\DateTimeInterface $aujourdhui): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.actif = true')
            ->andWhere('v.dateDepart <= :today')
            ->setParameter('today', $aujourdhui->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    public function countActifs(): int
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.actif = true')
            ->andWhere('v.dateDepart > :now')
            ->setParameter('now', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
