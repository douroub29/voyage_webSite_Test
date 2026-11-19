<?php

namespace App\Repository;

use App\Entity\Hotel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HotelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hotel::class);
    }

    public function findByDestination(int $destinationId): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.destination = :dest')
            ->andWhere('h.actif = true')
            ->setParameter('dest', $destinationId)
            ->orderBy('h.etoiles', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActifs(): array
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.destination', 'd')
            ->addSelect('d')
            ->where('h.actif = true')
            ->orderBy('h.etoiles', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
