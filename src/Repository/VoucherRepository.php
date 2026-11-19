<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Voucher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VoucherRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voucher::class);
    }

    /** Plus ancien voucher non encore utilisé du client. */
    public function findPremierDisponible(Client $client): ?Voucher
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.reservation', 'r')
            ->where('v.client = :client')
            ->andWhere('r.id IS NULL')
            ->setParameter('client', $client)
            ->orderBy('v.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countPourClient(Client $client): int
    {
        return (int) $this->count(['client' => $client]);
    }

    /** @return Voucher[] */
    public function findPourClient(Client $client): array
    {
        return $this->findBy(['client' => $client], ['createdAt' => 'DESC']);
    }
}
