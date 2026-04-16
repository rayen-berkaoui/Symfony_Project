<?php

namespace App\Repository;

use App\Entity\HistoriqueAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistoriqueAction>
 */
class HistoriqueActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueAction::class);
    }

    /**
     * @return HistoriqueAction[]
     */
    public function findLatest(int $limit = 20): array
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.utilisateur', 'u')
            ->addSelect('u')
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}