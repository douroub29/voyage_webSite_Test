<?php

namespace App\Repository;

use App\Entity\Chauffeur;
use App\Enum\TypeTransfert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChauffeurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chauffeur::class);
    }

    public function findDisponibles(\DateTimeInterface $date, TypeTransfert $type): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.disponibilites', 'd')
            ->where('c.actif = true')
            ->andWhere('d.date = :date')
            ->andWhere('d.estDisponible = true')
            ->andWhere('d.typeTrajet = :type OR d.typeTrajet = :lesDeux')
            ->setParameter('date', $date)
            ->setParameter('type', $type)
            ->setParameter('lesDeux', TypeTransfert::LesDeux)
            ->getQuery()
            ->getResult();
    }
}
